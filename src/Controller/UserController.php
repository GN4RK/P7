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
use Symfony\Component\Serializer\SerializerInterface ;

class UserController extends AbstractController
{
    #[Route('api/users/{id}', name: 'user_list', methods: ['GET'])]
    public function getUserList(Customer $customer, SerializerInterface $serializer): JsonResponse
    {
        $jsonUsers = $serializer->serialize($customer->getUsers(), 'json', ['groups' => 'getUsers']);
        return new JsonResponse($jsonUsers, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('api/users/{customerId}/{userId}', name: 'user_details', methods: ['GET'])]
    public function getUserDetails(
        int $customerId, int $userId, 
        CustomerRepository $customerRepository, 
        UserRepository $userRepository, SerializerInterface $serializer
        ): JsonResponse
    {
        // TODO Authentication
        $customer = $customerRepository->find($customerId);
        $user = $userRepository->find($userId);
        $jsonUsers = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);
        return new JsonResponse($jsonUsers, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('api/users/{customerId}/{userId}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(
        int $customerId, int $userId, EntityManagerInterface $em, CustomerRepository $customerRepository, 
        UserRepository $userRepository): JsonResponse
    {
        // TODO Authentication
        $customer = $customerRepository->find($customerId);
        $user = $userRepository->find($userId);
        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('api/users/{id}', name: 'add_user', methods: ['POST'])]
    public function addUser(
        Request $request, Customer $customer, SerializerInterface $serializer, 
        EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator): JsonResponse
    {

        $user = $serializer->deserialize($request->getContent(), User::class, 'json');
        $user->setCustomer($customer);
        $em->persist($user);
        $em->flush();

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);
        $location = $urlGenerator->generate('user_details', [
            'customerId' => $customer->getId(),
            'userId' => $user->getId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }
}
