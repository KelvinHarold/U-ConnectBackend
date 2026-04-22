<?php
// database/seeders/CategoryProductSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Faker\Factory as Faker;

class CategoryProductSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();
        
        // First, ensure we have a seller user
        $seller = User::role('seller')->first();
        if (!$seller) {
            $seller = User::create([
                'name' => 'Demo Seller',
                'email' => 'seller@example.com',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
            $seller->assignRole('seller');
        }
        
        // ========== PARENT CATEGORIES WITH THEIR SUBCATEGORIES ==========
        
        $categories = [
            [
                'name' => 'Electronics',
                'description' => 'Latest electronics and gadgets',
                'image' => '/storage/categories/electronics.jpg',
                'subcategories' => [
                    ['name' => 'Smartphones', 'description' => 'Latest smartphones from top brands'],
                    ['name' => 'Laptops', 'description' => 'Powerful laptops for work and gaming'],
                    ['name' => 'Tablets', 'description' => 'Portable tablets for entertainment'],
                    ['name' => 'Headphones', 'description' => 'Wireless and wired headphones'],
                    ['name' => 'Smart Watches', 'description' => 'Fitness and smart watches'],
                ]
            ],
            [
                'name' => 'Fashion',
                'description' => 'Trendy fashion for everyone',
                'image' => '/storage/categories/fashion.jpg',
                'subcategories' => [
                    ['name' => "Men's Clothing", 'description' => 'Shirts, pants, jackets for men'],
                    ['name' => "Women's Clothing", 'description' => 'Dresses, tops, skirts for women'],
                    ['name' => "Kids' Clothing", 'description' => 'Cute clothes for children'],
                    ['name' => 'Shoes', 'description' => 'Sneakers, boots, formal shoes'],
                    ['name' => 'Accessories', 'description' => 'Bags, belts, jewelry'],
                ]
            ],
            [
                'name' => 'Home & Living',
                'description' => 'Make your home beautiful',
                'image' => '/storage/categories/home-living.jpg',
                'subcategories' => [
                    ['name' => 'Furniture', 'description' => 'Sofas, tables, beds'],
                    ['name' => 'Kitchen Appliances', 'description' => 'Microwaves, blenders, cookware'],
                    ['name' => 'Home Decor', 'description' => 'Wall art, vases, lighting'],
                    ['name' => 'Bedding & Bath', 'description' => 'Sheets, towels, pillows'],
                    ['name' => 'Storage & Organization', 'description' => 'Shelves, bins, organizers'],
                ]
            ],
            [
                'name' => 'Sports & Outdoors',
                'description' => 'Gear for active lifestyle',
                'image' => '/storage/categories/sports.jpg',
                'subcategories' => [
                    ['name' => 'Fitness Equipment', 'description' => 'Dumbbells, yoga mats, treadmills'],
                    ['name' => 'Camping Gear', 'description' => 'Tents, sleeping bags, backpacks'],
                    ['name' => 'Cycling', 'description' => 'Bikes, helmets, accessories'],
                    ['name' => 'Team Sports', 'description' => 'Soccer, basketball, football gear'],
                    ['name' => 'Swimming', 'description' => 'Swimsuits, goggles, floats'],
                ]
            ],
            [
                'name' => 'Beauty & Personal Care',
                'description' => 'Look and feel your best',
                'image' => '/storage/categories/beauty.jpg',
                'subcategories' => [
                    ['name' => 'Skincare', 'description' => 'Moisturizers, cleansers, serums'],
                    ['name' => 'Makeup', 'description' => 'Foundation, lipstick, eyeshadow'],
                    ['name' => 'Hair Care', 'description' => 'Shampoo, conditioner, styling tools'],
                    ['name' => 'Fragrances', 'description' => 'Perfumes and colognes'],
                    ['name' => 'Men\'s Grooming', 'description' => 'Razors, trimmers, beard oil'],
                ]
            ],
            [
                'name' => 'Toys & Games',
                'description' => 'Fun for all ages',
                'image' => '/storage/categories/toys.jpg',
                'subcategories' => [
                    ['name' => 'Action Figures', 'description' => 'Superheroes, movie characters'],
                    ['name' => 'Board Games', 'description' => 'Family and strategy games'],
                    ['name' => 'Educational Toys', 'description' => 'Learning and development toys'],
                    ['name' => 'Outdoor Toys', 'description' => 'Bubbles, kites, water guns'],
                    ['name' => 'Video Games', 'description' => 'Games for all platforms'],
                ]
            ],
            [
                'name' => 'Books & Media',
                'description' => 'Expand your mind',
                'image' => '/storage/categories/books.jpg',
                'subcategories' => [
                    ['name' => 'Fiction', 'description' => 'Novels, thrillers, romance'],
                    ['name' => 'Non-Fiction', 'description' => 'Biographies, history, science'],
                    ['name' => 'Children\'s Books', 'description' => 'Picture books, early readers'],
                    ['name' => 'Audiobooks', 'description' => 'Digital audio books'],
                    ['name' => 'E-books', 'description' => 'Digital downloads'],
                ]
            ],
            [
                'name' => 'Automotive',
                'description' => 'Parts and accessories for your vehicle',
                'image' => '/storage/categories/automotive.jpg',
                'subcategories' => [
                    ['name' => 'Car Electronics', 'description' => 'GPS, dash cams, stereos'],
                    ['name' => 'Interior Accessories', 'description' => 'Seat covers, floor mats'],
                    ['name' => 'Exterior Accessories', 'description' => 'Car covers, spoilers'],
                    ['name' => 'Tools & Equipment', 'description' => 'Jacks, wrenches, diagnostic tools'],
                    ['name' => 'Motorcycle Parts', 'description' => 'Helmets, gloves, accessories'],
                ]
            ],
            [
                'name' => 'Health & Wellness',
                'description' => 'Products for healthy living',
                'image' => '/storage/categories/health.jpg',
                'subcategories' => [
                    ['name' => 'Vitamins & Supplements', 'description' => 'Multivitamins, protein powders'],
                    ['name' => 'Medical Supplies', 'description' => 'First aid, monitors, braces'],
                    ['name' => 'Fitness Trackers', 'description' => 'Activity bands, smart scales'],
                    ['name' => 'Massage Tools', 'description' => 'Massagers, foam rollers'],
                    ['name' => 'Essential Oils', 'description' => 'Aromatherapy oils, diffusers'],
                ]
            ],
            [
                'name' => 'Pet Supplies',
                'description' => 'Everything for your furry friends',
                'image' => '/storage/categories/pets.jpg',
                'subcategories' => [
                    ['name' => 'Dog Supplies', 'description' => 'Food, toys, beds, leashes'],
                    ['name' => 'Cat Supplies', 'description' => 'Litter, scratching posts, toys'],
                    ['name' => 'Bird Supplies', 'description' => 'Cages, feed, perches'],
                    ['name' => 'Fish Supplies', 'description' => 'Tanks, filters, decorations'],
                    ['name' => 'Small Animal', 'description' => 'Hamster, rabbit, guinea pig supplies'],
                ]
            ],
        ];
        
        // Create parent categories and their subcategories
        foreach ($categories as $parentData) {
            // Create parent category
            $parentCategory = Category::create([
                'name' => $parentData['name'],
                'slug' => Str::slug($parentData['name']),
                'description' => $parentData['description'],
                'image' => $parentData['image'],
                'parent_id' => null,
                'is_active' => true,
            ]);
            
            // Create subcategories
            foreach ($parentData['subcategories'] as $subData) {
                $subcategory = Category::create([
                    'name' => $subData['name'],
                    'slug' => Str::slug($subData['name']),
                    'description' => $subData['description'],
                    'image' => '/storage/subcategories/' . Str::slug($subData['name']) . '.jpg',
                    'parent_id' => $parentCategory->id,
                    'is_active' => true,
                ]);
                
                // Create 5-10 products for each subcategory
                $numProducts = rand(5, 10);
                for ($i = 0; $i < $numProducts; $i++) {
                    $this->createProductForCategory($subcategory->id, $seller->id, $faker);
                }
            }
        }
        
        $this->command->info('Categories, subcategories, and products seeded successfully!');
    }
    
    private function createProductForCategory($categoryId, $sellerId, $faker)
    {
        $category = Category::find($categoryId);
        $productNames = $this->getProductNamesForCategory($category->name);
        $productName = $faker->randomElement($productNames);
        
        $price = $faker->randomFloat(2, 10, 500);
        $quantity = rand(10, 500);
        
        $product = Product::create([
            'name' => $productName,
            'slug' => Str::slug($productName) . '-' . uniqid(),
            'description' => $faker->paragraphs(3, true),
            'price' => $price,
            'quantity' => $quantity,
            'min_stock_alert' => rand(5, 20),
            'image' => $this->getProductImageForCategory($category->name),
            'images' => json_encode($this->getMultipleImagesForProduct($category->name)),
            'seller_id' => $sellerId,
            'category_id' => $categoryId,
            'is_active' => true,
            'is_featured' => rand(0, 1),
            'views_count' => rand(0, 5000),
            'sales_count' => rand(0, 1000),
        ]);
        
        // Log initial inventory
        $product->logInventoryChange('add', $quantity, 0, 'Initial stock from seeder');
        
        return $product;
    }
    
    private function getProductNamesForCategory($categoryName)
    {
        $products = [
            'Smartphones' => [
                'iPhone 15 Pro Max', 'Samsung Galaxy S24 Ultra', 'Google Pixel 8 Pro',
                'OnePlus 12', 'Xiaomi 14 Pro', 'iPhone 15', 'Samsung Galaxy S24',
                'Nothing Phone 2', 'Pixel 8', 'Motorola Edge 40'
            ],
            'Laptops' => [
                'MacBook Pro M3', 'Dell XPS 15', 'HP Spectre x360', 'Lenovo ThinkPad X1',
                'ASUS ROG Zephyrus', 'Acer Predator Helios', 'Microsoft Surface Laptop',
                'LG Gram', 'Razer Blade 15', 'MSI Stealth'
            ],
            "Men's Clothing" => [
                'Classic Cotton T-Shirt', 'Slim Fit Jeans', 'Casual Blazer',
                'Polo Shirt', 'Hoodie', 'Leather Jacket', 'Chino Pants',
                'Formal Shirt', 'Sweater', 'Shorts'
            ],
            "Women's Clothing" => [
                'Floral Summer Dress', 'High Waist Jeans', 'Silk Blouse',
                'Knit Sweater', 'Maxi Dress', 'Leather Skirt', 'Cardigan',
                'Blazer', 'Jumpsuit', 'Pleated Skirt'
            ],
            'Furniture' => [
                'Modern Sofa', 'Dining Table Set', 'Memory Foam Mattress',
                'Bookshelf', 'Coffee Table', 'Office Chair', 'Nightstand',
                'Wardrobe', 'Desk', 'Recliner'
            ],
            'Fitness Equipment' => [
                'Adjustable Dumbbells', 'Yoga Mat', 'Treadmill', 'Exercise Bike',
                'Resistance Bands', 'Pull Up Bar', 'Kettlebell Set',
                'Rowing Machine', 'Jump Rope', 'Weight Bench'
            ],
            'Skincare' => [
                'Hydrating Face Cream', 'Vitamin C Serum', 'Gentle Face Wash',
                'Exfoliating Scrub', 'Eye Cream', 'Sunscreen SPF 50',
                'Night Repair Cream', 'Face Mask', 'Toner', 'Moisturizer'
            ],
            'Board Games' => [
                'Monopoly', 'Chess Set', 'Scrabble', 'Uno', 'Risk',
                'Catan', 'Ticket to Ride', 'Codenames', 'Clue', 'Jenga'
            ],
        ];
        
        // Return products for specific category or default
        foreach ($products as $key => $productList) {
            if (strpos($categoryName, $key) !== false) {
                return $productList;
            }
        }
        
        // Default products for any category
        return [
            'Premium Product', 'Deluxe Edition', 'Standard Model',
            'Pro Version', 'Elite Series', 'Classic Design',
            'Modern Style', 'Essential Pack', 'Advanced Model', 'Ultimate Edition'
        ];
    }
    
    private function getProductImageForCategory($categoryName)
    {
        $images = [
            'Smartphones' => '/storage/products/iphone15.jpg',
            'Laptops' => '/storage/products/macbook-pro.jpg',
            "Men's Clothing" => '/storage/products/mens-tshirt.jpg',
            "Women's Clothing" => '/storage/products/womens-dress.jpg',
            'Furniture' => '/storage/products/modern-sofa.jpg',
            'Fitness Equipment' => '/storage/products/dumbbells.jpg',
            'Skincare' => '/storage/products/skincare-set.jpg',
            'Board Games' => '/storage/products/board-games.jpg',
        ];
        
        foreach ($images as $key => $image) {
            if (strpos($categoryName, $key) !== false) {
                return $image;
            }
        }
        
        return '/storage/products/default-product.jpg';
    }
    
    private function getMultipleImagesForProduct($categoryName)
    {
        $images = [];
        $numImages = rand(1, 4);
        
        for ($i = 1; $i <= $numImages; $i++) {
            $images[] = '/storage/products/product-' . rand(1, 20) . '.jpg';
        }
        
        return $images;
    }
}