<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dish;

class DishSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dishes = [
            [
                'name_bn' => 'কিং প্রন',
                'name_en' => 'King Prawn',
                'price'   => 550,
                'category'=> 'Mains',
                'photo_path' => '/images/Dish/King Prawn.png',
            ],
            [
                'name_bn' => 'ইলিশ ভাপা',
                'name_en' => 'Steamed Hilsa',
                'price'   => 450,
                'category'=> 'Mains',
                'photo_path' => '/images/Dish/Steamed Hilsa.png',
            ],
            [
                'name_bn' => 'লইট্টা ফ্রাই',
                'name_en' => 'Loitta fry',
                'price'   => 450,
                'category'=> 'Mains',
                'photo_path' => '/images/Dish/Loitta fry.png',
            ],
            [
                'name_bn' => 'খাসির রেজালা',
                'name_en' => 'Mutton Rezala',
                'price'   => 600,
                'category'=> 'Mains',
                'photo_path' => '/images/Dish/Mutton Rezala.png',
            ],
            [
                'name_bn' => 'রুই মাছ',
                'name_en' => 'Rui Fish',
                'price'   => 500,
                'category'=> 'Mains',
                'photo_path' => '/images/Dish/Rui Fish.png',
            ],
            [
                'name_bn' => 'ভুনা খিচুড়ি চিংড়ি',
                'name_en' => 'Prawn Khichuri',
                'price'   => 700,
                'category'=> 'Mains',
                'photo_path' => '/images/Dish/Prawn Khichuri.png',
            ],
            [
                'name_bn' => 'ভুনা খিচুড়ি মুরগি',
                'name_en' => 'Chicken Khichuri',
                'price'   => 550,
                'category'=> 'Mains',
                'photo_path' => '/images/Dish/Chicken Khichuri.png',
            ],
            [
                'name_bn' => 'ভাত',
                'name_en' => 'Rice',
                'price'   => 150,
                'category'=> 'Mains',
                'photo_path' => '/images/Dish/Rice.png',
            ],
            [
                'name_bn' => 'পেঁয়াজি',
                'name_en' => 'Onion Fritter',
                'price'   => 250,
                'category'=> 'Appetizers',
                'photo_path' => '/images/Dish/Onion Fritter.png',
            ],
            [
                'name_bn' => 'মোচার চপ ',
                'name_en' => 'Mushroom Chop',
                'price'   => 300,
                'category'=> 'Appetizers',
                'photo_path' => '/images/Dish/Mushroom Chop.png',
            ],
            [
                'name_bn' => 'ডিম ডেভিল',
                'name_en' => 'Devil Egg',
                'price'   => 400,
                'category'=> 'Appetizers',
                'photo_path' => '/images/Dish/Devil Egg.png',
            ],
            [
                'name_bn' => 'মাছের কাটলেট',
                'name_en' => 'Fish Cutlet',
                'price'   => 300,
                'category'=> 'Appetizers',
                'photo_path' => '/images/Dish/Fish Cutlet.png',
            ],
            [
                'name_bn' => 'রসমালাই',
                'name_en' => 'Rasmalai',
                'price'   => 500,
                'category'=> 'Desserts',
                'photo_path' => '/images/Dish/Rasmalai.png',
            ],
                    [
                'name_bn' => 'ফিরনি',
                'name_en' => 'Firni',
                'price'   => 200,
                'category'=> 'Desserts',
                'photo_path' => '/images/Dish/Firni.png',
            ],
            [
                'name_bn' => 'চমচম',
                'name_en' => 'Chomchom',
                'price'   => 400,
                'category'=> 'Desserts',
                'photo_path' => '/images/Dish/Chomchom.png',
            ],
            [
                'name_bn' => 'সন্দেশ',
                'name_en' => 'Sandesh',
                'price'   => 450,
                'category'=> 'Desserts',
                'photo_path' => '/images/Dish/Sandesh.png',
            ],
            [
                'name_bn' => 'মিষ্টি লাচ্ছি',
                'name_en' => 'Sweet Lassi',
                'price'   => 200,
                'category'=> 'Drinks',
                'photo_path' => '/images/Dish/Sweet Lassi.png',
            ],
            [
                'name_bn' => 'বোরহানি',
                'name_en' => 'Borhani',
                'price'   => 200,
                'category'=> 'Drinks',
                'photo_path' => '/images/Dish/Borhani.png',
            ],
            [
                'name_bn' => 'আমের লাচ্ছি',
                'name_en' => 'Mango Lassi',
                'price'   => 200,
                'category'=> 'Drinks',
                'photo_path' => '/images/Dish/Mango Lassi.png',
            ],
        ];

        foreach ($dishes as $data) {
            Dish::updateOrCreate([
                'name_en' => $data['name_en'],
            ], $data);
        }
    }
}
