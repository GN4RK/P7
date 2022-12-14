<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class UserController extends AbstractController
{
    /**
     * This function return the user list for a specific customer.
     *
     * @OA\Response(
     *     response=200,
     *     description="Return the user list for a specific customer",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class))
     *     )
     * )
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Customer id",
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="The page we want to get",
     *     @OA\Schema(type="int")
     * )
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Number of item desired",
     *     @OA\Schema(type="int")
     * )
     * @OA\Response(
     *     response=401,
     *     description="Invalid credentials"
     * )
     * @OA\Tag(name="User")
     *
     * @param Customer $customer
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('api/users/{id}', name: 'user_list', methods: ['GET'])]
    public function getUserList(Customer $customer, SerializerInterface $serializer, 
        Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // Checking access (customer can only access his own user list)
        $loggedCustomer = $this->getUser();
        if ($loggedCustomer->getId() != $customer->getId()) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Invalid credentials.');
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 50);

        $idCache = "getUserList-" . $page . "-" . $limit;
        $jsonUserList = $cachePool->get($idCache, function (ItemInterface $item) use ($page, $limit, $serializer) {
            $item->tag("usersCache");
            $userList = $this->getUser()->getUsersWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getUsers']);
            return $serializer->serialize($userList, 'json', $context);
        });

        return new JsonResponse($jsonUserList, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * This function return the user list details.
     *
     * @OA\Response(
     *     response=200,
     *     description="Return the user details",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class))
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Invalid credentials"
     * )
     * @OA\Response(
     *     response=404,
     *     description="User not found"
     * )
     * @OA\Tag(name="User")
     *
     * @param Customer $customer
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('api/users/{customerId}/{userId}', name: 'user_details', methods: ['GET'])]
    public function getUserDetails(
        int $customerId, int $userId, 
        CustomerRepository $customerRepository, 
        UserRepository $userRepository, SerializerInterface $serializer
        ): JsonResponse
    {
        // Checking access (customer can only access his own users)
        $customer = $customerRepository->find($customerId);
        $loggedCustomer = $this->getUser();
        if ($loggedCustomer->getId() != $customer->getId()) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Invalid credentials.');
        }

        // check if user is not empty
        $user = $userRepository->find($userId);
        if (empty($user)) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'User not found.');
        }
        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUsers = $serializer->serialize($user, 'json', $context);
        return new JsonResponse($jsonUsers, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * This function delete a user.
     *
     * @OA\Response(
     *     response=204,
     *     description="Delete a user"
     * )
     * @OA\Response(
     *     response=401,
     *     description="Invalid credentials"
     * )
     * @OA\Tag(name="User")
     *
     * @param Customer $customer
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('api/users/{customerId}/{userId}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(
        int $customerId, int $userId, EntityManagerInterface $em, 
        CustomerRepository $customerRepository, UserRepository $userRepository, 
        SerializerInterface $serializer, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // Checking access (customer can only access his own user list)
        $customer = $customerRepository->find($customerId);
        $loggedCustomer = $this->getUser();
        if ($loggedCustomer->getId() != $customer->getId()) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Invalid credentials.');
        }
        $user = $userRepository->find($userId);
        $em->remove($user);
        $em->flush();

        // invalid cache
        $cachePool->invalidateTags(["usersCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * This function add a user to a specific customer.
     *
     * @OA\Response(
     *     response=201,
     *     description="Return the user added to the customer",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class))
     *     )
     * )
     * @OA\Response(
     *     response=400,
     *     description="Bad Request"
     * )
     * @OA\Response(
     *     response=401,
     *     description="Invalid credentials"
     * )
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Customer id",
     * )
     * @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(
     *         example={
     *             "username": "test.add",
     *             "email":    "test.add@example.com",
     *             "password": "password"
     *         },
     *         @OA\Schema (
     *              type="object",
     *              @OA\Property(property="username", required=true, description="Username", type="string"),
     *              @OA\Property(property="email", required=true, description="Valid email adress", type="string"),
     *              @OA\Property(property="password", required=true, description="Hashed password", type="string"),
     *         )
     *     )
     * )
     * @OA\Tag(name="User")
     *
     * @param Customer $customer
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('api/users/{id}', name: 'add_user', methods: ['POST'])]
    public function addUser(
        Request $request, Customer $customer, SerializerInterface $serializer, 
        EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // Checking access (customer can only access his own user list)
        $loggedCustomer = $this->getUser();
        if ($loggedCustomer->getId() != $customer->getId()) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Invalid credentials.');
        }

        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $errors = $validator->validate($user);
        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user->setCustomer($customer);
        $em->persist($user);
        $em->flush();

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);
        $location = $urlGenerator->generate('user_details', [
            'customerId' => $customer->getId(),
            'userId' => $user->getId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // invalid cache
        $cachePool->invalidateTags(["usersCache"]);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * This function update a user to a specific customer.
     * @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(
     *         example={
     *             "username": "test.add",
     *             "email":    "test.add@example.com",
     *             "password": "password"
     *         },
     *         @OA\Schema (
     *              type="object",
     *              @OA\Property(property="username", required=true, description="Username", type="string"),
     *              @OA\Property(property="email", required=true, description="Valid email adress", type="string"),
     *              @OA\Property(property="password", required=true, description="Hashed password", type="string"),
     *         )
     *     )
     * )
     * @OA\Response(
     *     response=400,
     *     description="Bad Request"
     * )
     * @OA\Response(
     *     response=401,
     *     description="Invalid credentials"
     * )
     * @OA\Response(
     *     response=404,
     *     description="User not found"
     * )
     * @OA\Tag(name="User")
     *
     */
    #[Route('api/users/{customerId}/{userId}', name: 'update_user', methods: ['PUT'])]
    public function updateUser(
        int $customerId, int $userId, Request $request, EntityManagerInterface $em, 
        CustomerRepository $customerRepository, UserRepository $userRepository, 
        SerializerInterface $serializer, ValidatorInterface $validator, 
        UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // Checking access (customer can only access his own users)
        $customer = $customerRepository->find($customerId);
        $loggedCustomer = $this->getUser();
        if ($loggedCustomer->getId() != $customer->getId()) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Invalid credentials.');
        }

        $user = $userRepository->find($userId);
        if (empty($user)) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'User not found.');
        }

        $updatedUser = $serializer->deserialize(
            $request->getContent(), 
            User::class, 
            'json'
        );

        $user->setUsername($updatedUser->getUsername());
        $user->setEmail($updatedUser->getEmail());
        $user->setPassword($updatedUser->getPassword());

        $errors = $validator->validate($user);
        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user->setCustomer($loggedCustomer);
        $em->persist($user);
        $em->flush();

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);
        $location = $urlGenerator->generate('user_details', [
            'customerId' => $customer->getId(),
            'userId' => $user->getId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // invalid cache
        $cachePool->invalidateTags(["usersCache"]);

        return new JsonResponse($jsonUser, Response::HTTP_OK, ["Location" => $location], true);
    }
}
