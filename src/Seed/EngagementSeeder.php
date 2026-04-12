<?php

declare(strict_types=1);

namespace App\Seed;

final class EngagementSeeder
{
    /**
     * Seed users with varied roles across 3 communities.
     *
     * Community indices: 0 = Sagamok, 1 = Wikwemikong, 2 = Garden River
     *
     * @return list<array{name: string, mail: string, roles: list<string>, status: int, community_index: int}>
     */
    public static function users(): array
    {
        return [
            ['name' => 'sarah_m', 'mail' => 'sarah@example.com', 'roles' => [], 'status' => 1, 'community_index' => 0],
            ['name' => 'mike_w', 'mail' => 'mike@example.com', 'roles' => [], 'status' => 1, 'community_index' => 1],
            ['name' => 'lisa_g', 'mail' => 'lisa@example.com', 'roles' => [], 'status' => 1, 'community_index' => 2],
            ['name' => 'elder_jean', 'mail' => 'jean@example.com', 'roles' => ['elder'], 'status' => 1, 'community_index' => 0],
            ['name' => 'vol_david', 'mail' => 'david@example.com', 'roles' => ['volunteer'], 'status' => 1, 'community_index' => 1],
            ['name' => 'coord_anna', 'mail' => 'anna@example.com', 'roles' => ['coordinator'], 'status' => 1, 'community_index' => 2],
        ];
    }

    /**
     * Seed posts with community-voice content.
     *
     * @return list<array{body: string, user_index: int, community_index: int}>
     */
    public static function posts(): array
    {
        return [
            ['body' => 'The maple syrup run started early this year. We tapped about forty trees behind the community centre last weekend.', 'user_index' => 0, 'community_index' => 0],
            ['body' => 'Reminder: language class every Wednesday at 6 PM in the band hall. All levels welcome — bring a notebook.', 'user_index' => 3, 'community_index' => 0],
            ['body' => 'Looking for volunteers to help set up for the spring feast next Saturday. We need hands starting at 9 AM.', 'user_index' => 4, 'community_index' => 1],
            ['body' => 'Just finished beading a pair of moccasins for my niece. Three months of work and they turned out beautiful.', 'user_index' => 1, 'community_index' => 1],
            ['body' => 'The youth drum group performed at the school assembly today. Those kids are really coming along.', 'user_index' => 2, 'community_index' => 2],
            ['body' => 'Elder Jean shared the teaching about the seven grandfather teachings at circle last night. Miigwech for that wisdom.', 'user_index' => 0, 'community_index' => 0],
            ['body' => 'Road to the lake is washed out after the rain. Take the back road past the church if you need to get through.', 'user_index' => 5, 'community_index' => 2],
            ['body' => 'Our community garden plots are open for signup. Stop by the band office to reserve yours before they fill up.', 'user_index' => 5, 'community_index' => 2],
            ['body' => 'Anyone know a good recipe for wild rice soup? Making a big batch for the Elders lunch on Thursday.', 'user_index' => 4, 'community_index' => 1],
            ['body' => 'Congratulations to our high school graduates! Six students walking the stage this year. We are proud of you.', 'user_index' => 3, 'community_index' => 0],
            ['body' => 'The healing lodge is hosting a sweat this Friday evening. Contact the coordinator if you would like to participate.', 'user_index' => 3, 'community_index' => 0],
            ['body' => 'Caught a beautiful walleye off the point this morning. The fishing has been really good this week.', 'user_index' => 1, 'community_index' => 1],
        ];
    }

    /**
     * Seed reactions across posts and users, mixing all 5 reaction types.
     *
     * @return list<array{reaction_type: string, user_index: int, target_type: string, post_index: int}>
     */
    public static function reactions(): array
    {
        return [
            // Post 0 (maple syrup) — 4 reactions
            ['reaction_type' => 'miigwech', 'user_index' => 1, 'target_type' => 'post', 'post_index' => 0],
            ['reaction_type' => 'like', 'user_index' => 2, 'target_type' => 'post', 'post_index' => 0],
            ['reaction_type' => 'interested', 'user_index' => 3, 'target_type' => 'post', 'post_index' => 0],
            ['reaction_type' => 'like', 'user_index' => 4, 'target_type' => 'post', 'post_index' => 0],
            // Post 1 (language class) — 3 reactions
            ['reaction_type' => 'interested', 'user_index' => 0, 'target_type' => 'post', 'post_index' => 1],
            ['reaction_type' => 'miigwech', 'user_index' => 1, 'target_type' => 'post', 'post_index' => 1],
            ['reaction_type' => 'interested', 'user_index' => 4, 'target_type' => 'post', 'post_index' => 1],
            // Post 2 (volunteers) — 3 reactions
            ['reaction_type' => 'connect', 'user_index' => 0, 'target_type' => 'post', 'post_index' => 2],
            ['reaction_type' => 'connect', 'user_index' => 2, 'target_type' => 'post', 'post_index' => 2],
            ['reaction_type' => 'interested', 'user_index' => 3, 'target_type' => 'post', 'post_index' => 2],
            // Post 3 (moccasins) — 3 reactions
            ['reaction_type' => 'miigwech', 'user_index' => 0, 'target_type' => 'post', 'post_index' => 3],
            ['reaction_type' => 'like', 'user_index' => 3, 'target_type' => 'post', 'post_index' => 3],
            ['reaction_type' => 'recommend', 'user_index' => 5, 'target_type' => 'post', 'post_index' => 3],
            // Post 4 (youth drum) — 2 reactions
            ['reaction_type' => 'miigwech', 'user_index' => 3, 'target_type' => 'post', 'post_index' => 4],
            ['reaction_type' => 'like', 'user_index' => 5, 'target_type' => 'post', 'post_index' => 4],
            // Post 5 (Elder teachings) — 4 reactions
            ['reaction_type' => 'miigwech', 'user_index' => 1, 'target_type' => 'post', 'post_index' => 5],
            ['reaction_type' => 'miigwech', 'user_index' => 2, 'target_type' => 'post', 'post_index' => 5],
            ['reaction_type' => 'miigwech', 'user_index' => 4, 'target_type' => 'post', 'post_index' => 5],
            ['reaction_type' => 'recommend', 'user_index' => 5, 'target_type' => 'post', 'post_index' => 5],
            // Post 6 (road washed out) — 2 reactions
            ['reaction_type' => 'like', 'user_index' => 0, 'target_type' => 'post', 'post_index' => 6],
            ['reaction_type' => 'like', 'user_index' => 1, 'target_type' => 'post', 'post_index' => 6],
            // Post 9 (graduates) — 3 reactions
            ['reaction_type' => 'miigwech', 'user_index' => 0, 'target_type' => 'post', 'post_index' => 9],
            ['reaction_type' => 'like', 'user_index' => 1, 'target_type' => 'post', 'post_index' => 9],
            ['reaction_type' => 'like', 'user_index' => 2, 'target_type' => 'post', 'post_index' => 9],
            // Post 10 (healing lodge) — 2 reactions
            ['reaction_type' => 'interested', 'user_index' => 2, 'target_type' => 'post', 'post_index' => 10],
            ['reaction_type' => 'interested', 'user_index' => 4, 'target_type' => 'post', 'post_index' => 10],
            // Post 11 (walleye) — 2 reactions
            ['reaction_type' => 'like', 'user_index' => 0, 'target_type' => 'post', 'post_index' => 11],
            ['reaction_type' => 'like', 'user_index' => 5, 'target_type' => 'post', 'post_index' => 11],
        ];
    }

    /**
     * Seed comments on posts.
     *
     * @return list<array{body: string, user_index: int, target_type: string, post_index: int}>
     */
    public static function comments(): array
    {
        return [
            ['body' => 'How many litres did you get? We had a good run too.', 'user_index' => 1, 'target_type' => 'post', 'post_index' => 0],
            ['body' => 'About twenty litres so far. The warm days and cold nights are perfect.', 'user_index' => 0, 'target_type' => 'post', 'post_index' => 0],
            ['body' => 'I will be there! Been wanting to brush up on my greetings.', 'user_index' => 2, 'target_type' => 'post', 'post_index' => 1],
            ['body' => 'Count me in for setup. I can bring tables from the church.', 'user_index' => 0, 'target_type' => 'post', 'post_index' => 2],
            ['body' => 'Those are gorgeous! Do you take commissions?', 'user_index' => 2, 'target_type' => 'post', 'post_index' => 3],
            ['body' => 'My nokomis used to make hers with wild onion and a bit of sage. Keeps it simple.', 'user_index' => 3, 'target_type' => 'post', 'post_index' => 8],
            ['body' => 'So proud of these kids. They have worked really hard.', 'user_index' => 5, 'target_type' => 'post', 'post_index' => 9],
            ['body' => 'What time does it start?', 'user_index' => 0, 'target_type' => 'post', 'post_index' => 10],
            ['body' => 'Usually around 5 PM. Come a bit early to help with the fire.', 'user_index' => 3, 'target_type' => 'post', 'post_index' => 10],
            ['body' => 'Nice catch! We should plan a community fishing day this summer.', 'user_index' => 5, 'target_type' => 'post', 'post_index' => 11],
        ];
    }

    /**
     * Seed follows on communities and posts.
     *
     * @return list<array{user_index: int, target_type: string, target_index: int}>
     */
    public static function follows(): array
    {
        return [
            // Users following communities
            ['user_index' => 0, 'target_type' => 'community', 'target_index' => 0],
            ['user_index' => 1, 'target_type' => 'community', 'target_index' => 1],
            ['user_index' => 2, 'target_type' => 'community', 'target_index' => 2],
            ['user_index' => 3, 'target_type' => 'community', 'target_index' => 0],
            ['user_index' => 4, 'target_type' => 'community', 'target_index' => 1],
            // Users following posts
            ['user_index' => 1, 'target_type' => 'post', 'target_index' => 0],
            ['user_index' => 2, 'target_type' => 'post', 'target_index' => 2],
            ['user_index' => 0, 'target_type' => 'post', 'target_index' => 5],
        ];
    }

    /**
     * Community names to look up (ordered by index).
     *
     * @return list<string>
     */
    public static function communityNames(): array
    {
        return [
            'Sagamok Anishnawbek',
            'Wikwemikong Unceded Territory',
            'Garden River First Nation',
        ];
    }
}
