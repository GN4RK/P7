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

class UserController extends AbstractController
{
    #[Route('api/users/{id}', name: 'user_list', methods: ['GET'])]
    public function getUserList(Customer $customer, SerializerInterface $serializer): JsonResponse
    {
        // Checking access (customer can only access his own user list)
        $loggedCustomer = $this->getUser();
        if ($loggedCustomer->getId() != $customer->getId()) {
            $response = $serializer->serialize([
                'status' => '401',
                'message' => 'Invalid credentials.',
            ], 'json');
            return new JsonResponse($response, Response::HTTP_UNAUTHORIZED, ['accept' => 'json'], true);
        }

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUsers = $serializer->serialize($customer->getUsers(), 'json', $context);
        return new JsonResponse($jsonUsers, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('api/users/{customerId}/{userId}', name: 'user_details', methods: ['GET'])]
    public function getUserDetails(
        int $customerId, int $userId, 
        CustomerRepository $customerRepository, 
        UserRepository $userRepository, SerializerInterface $serializer
        ): JsonResponse
    {
        // Checking access (customer can only access his own user list)
        $customer = $customerRepository->find($customerId);
        $loggedCustomer = $this->getUser();
        if ($loggedCustomer->getId() != $customer->getId()) {
            $response = $serializer->serialize([
                'status' => '401',
                'message' => 'Invalid credentials.',
            ], 'json');
            return new JsonResponse($response, Response::HTTP_UNAUTHORIZED, ['accept' => 'json'], true);
        }

        // TODO check if user is not empty
        $user = $userRepository->find($userId);
        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUsers = $serializer->serialize($user, 'json', $context);
        return new JsonResponse($jsonUsers, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('api/users/{customerId}/{userId}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(
        int $customerId, int $userId, EntityManagerInterface $em, CustomerRepository $customerRepository, 
        UserRepository $userRepository, SerializerInterface $serializer): JsonResponse
    {
        // Checking access (customer can only access his own user list)
        $customer = $customerRepository->find($customerId);
        $loggedCustomer = $this->getUser();
        if ($loggedCustomer->getId() != $customer->getId()) {
            $response = $serializer->serialize([
                'status' => '401',
                'message' => 'Invalid credentials.',
            ], 'json');
            return new JsonResponse($response, Response::HTTP_UNAUTHORIZED, ['accept' => 'json'], true);
        }
        $user = $userRepository->find($userId);
        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('api/users/{id}', name: 'add_user', methods: ['POST'])]
    public function addUser(
        Request $request, Customer $customer, SerializerInterface $serializer, 
        EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator): JsonResponse
    {
        // Checking access (customer can only access his own user list)
        $loggedCustomer = $this->getUser();
        if ($loggedCustomer->getId() != $customer->getId()) {
            $response = $serializer->serialize([
                'status' => '401',
                'message' => 'Invalid credentials.',
            ], 'json');
            return new JsonResponse($response, Response::HTTP_UNAUTHORIZED, ['accept' => 'json'], true);
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

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }
}
