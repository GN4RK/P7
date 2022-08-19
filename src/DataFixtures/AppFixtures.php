<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

use App\Entity\Product;
use App\Entity\User;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('FR-fr');
        $faker->addProvider(new \Faker\Provider\fr_FR\Company($faker));

        // PRODUCTS
        for ($i = 0; $i < 100; $i++) {

            $product = new Product();

            $manufacturer = explode(" ", $faker->company())[0];
            $model = 
                ucfirst($faker->unique()->word()) . " " . 
                $faker->randomDigit() .
                ($faker->boolean(40) ? strtoupper($faker->randomLetter()) : "") .
                ($faker->boolean(40) ? " " . $faker->randomElement(["Pro", "Note"]) : "");

            $product->setName(
                $manufacturer . " " . 
                $model . " " . 
                $faker->randomElement([32 , 64, 128]) . "Go " .
                $faker->randomElement([
                    "Black",
                    "Grey",
                    "White",
                    "Red",
                ])
            );

            $product->setModel($model);
            $product->setDescription($faker->paragraph());
            $product->setManufacturer($manufacturer);
            $product->setPrice($faker->numberBetween(99, 500) * 100);

            $manager->persist($product);
        }

        // CUSTOMERS
        $customers = [];
        for ($i = 0; $i < 3; $i++) {
            $customer = new Customer();
            $customer->setName($faker->company());
            $customers[] = $customer;
            $manager->persist($customer);
        }

        // USERS
        for ($i = 0; $i < 200; $i++) {
            $user = new User();
            $user->setUsername($faker->userName());
            $user->setEmail($faker->safeEmail());
            $user->setPassword('test');
            $user->setCustomer($customers[array_rand($customers)]);
            $manager->persist($user);
        }

        $manager->flush();
    }
}
