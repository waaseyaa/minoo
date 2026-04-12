<?php

declare(strict_types=1);

namespace App\Seed;

final class PeopleSeeder
{
    /** @return list<array<string, mixed>> */
    public static function samplePeople(): array
    {
        return [
            [
                'name' => 'Mary Trudeau',
                'slug' => 'mary-trudeau',
                'bio' => "Mary has been catering community events in Sagamok for over fifteen years. She specializes in traditional dishes including bannock, wild rice soup, and smoked fish, as well as contemporary meals for large gatherings.\n\nHer business, Mary's Bannock & Catering, serves events ranging from small family celebrations to community-wide feasts. She is passionate about keeping traditional food practices alive while making them accessible for modern events.",
                'community' => 'Sagamok Anishnawbek',
                'roles' => ['Caterer', 'Small Business Owner'],
                'offerings' => ['Food'],
                'business_name' => "Mary's Bannock & Catering",
                'email' => 'mary@example.com',
                'phone' => '705-555-0101',
                'status' => 1,
            ],
            [
                'name' => 'John Beaucage',
                'slug' => 'john-beaucage',
                'bio' => "John is a respected Elder in the Sagamok community with deep knowledge of Anishinaabe governance traditions and treaty history. He is frequently called upon to provide opening prayers and cultural guidance for community events and government meetings.\n\nJohn is available for teachings on treaty rights, traditional governance, and land-based education. He has mentored many young leaders in the community over the past three decades.",
                'community' => 'Sagamok Anishnawbek',
                'roles' => ['Elder', 'Knowledge Keeper'],
                'offerings' => ['Teachings', 'Cultural Services'],
                'business_name' => '',
                'email' => 'john@example.com',
                'phone' => '705-555-0102',
                'status' => 1,
            ],
            [
                'name' => 'Sarah Owl',
                'slug' => 'sarah-owl',
                'bio' => "Sarah is a skilled regalia maker and beadwork artist from Garden River First Nation. She creates custom jingle dresses, fancy shawl regalia, and beadwork pieces for powwow dancers and community members across the region.\n\nThrough her business Owl Designs, Sarah also runs regular workshops teaching beadwork fundamentals, ribbon skirt making, and regalia construction. She believes in passing traditional crafting skills to the next generation.",
                'community' => 'Garden River First Nation',
                'roles' => ['Regalia Maker', 'Crafter', 'Workshop Facilitator'],
                'offerings' => ['Regalia', 'Beadwork', 'Workshops'],
                'business_name' => 'Owl Designs',
                'email' => 'sarah@example.com',
                'phone' => '705-555-0103',
                'status' => 1,
            ],
            [
                'name' => 'Mike Abitong',
                'slug' => 'mike-abitong',
                'bio' => "Mike leads land-based youth programs in Atikameksheng, teaching young people traditional harvesting practices including cedar picking, medicine gathering, and seasonal land stewardship.\n\nHe provides cedar bundles and other harvested materials for community ceremonies and events. Mike also runs week-long summer camps focused on reconnecting youth with the land through hands-on traditional activities.",
                'community' => 'Atikameksheng Anishnawbek',
                'roles' => ['Cedar Harvester', 'Youth Worker'],
                'offerings' => ['Cedar Products', 'Workshops', 'Cultural Services'],
                'business_name' => '',
                'email' => 'mike@example.com',
                'phone' => '705-555-0104',
                'status' => 1,
            ],
        ];
    }
}
