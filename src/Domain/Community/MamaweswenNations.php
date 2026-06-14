<?php

declare(strict_types=1);

namespace App\Domain\Community;

/**
 * The seven First Nations of Mamaweswen, The North Shore Tribal Council, all in
 * the Robinson-Huron Treaty (1850) territory on the north shore of Lake Huron,
 * anchored at Sagamok Anishnawbek (#798).
 *
 * This is FACTUAL PUBLIC DATA only — nation names, treaty, main reserve,
 * approximate community coordinates, and the elected leadership that appears on
 * the public Indigenous Services Canada First Nation Profiles. Leadership is a
 * snapshot (see AS_OF) with a link back to the authoritative ISC governance page
 * for the current record (#799). NO individual community-member listings live
 * here — those stay consent-gated (#800).
 */
final class MamaweswenNations
{
    /** Month the leadership snapshot was compiled from public band profiles. */
    public const string AS_OF = 'June 2026';

    /**
     * @return list<array{
     *     slug: string, name: string, band_number: int, treaty: string,
     *     reserve: string, lat: float, lng: float, population: int,
     *     chief: string, council: list<string>, website: string, anchor: bool
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'sagamok-anishnawbek',
                'name' => 'Sagamok Anishnawbek',
                'band_number' => 179,
                'treaty' => 'Robinson-Huron Treaty (1850)',
                'reserve' => 'Sagamok',
                'lat' => 46.167,
                'lng' => -82.217,
                'population' => 3295,
                'chief' => 'Angus Toulouse',
                'council' => [
                    'Anna Marie Abitong', 'Michael Abitong', 'Arnolda Bennett', 'Leroy Bennett',
                    'Nicole Eshkakogan', 'Paul Eshkakogan', 'Lawrence Solomon Sr.',
                    'Rhonda Stoneypoint-Trudeau', 'McKenzie Toulouse', 'Sheldon Toulouse', 'William Toulouse',
                ],
                'website' => 'https://www.sagamok.ca/',
                'anchor' => true,
            ],
            [
                'slug' => 'serpent-river',
                'name' => 'Serpent River First Nation',
                'band_number' => 201,
                'treaty' => 'Robinson-Huron Treaty (1850)',
                'reserve' => 'Serpent River 7',
                'lat' => 46.183,
                'lng' => -82.550,
                'population' => 1621,
                'chief' => 'Wilma Johnston',
                'council' => [
                    'Shirley Ahwanaquot', 'Kerri Commanda', 'Richard Measwasige', 'Michelle Owl', 'John Trudeau',
                ],
                'website' => 'https://www.serpentriverfn.com/',
                'anchor' => false,
            ],
            [
                'slug' => 'mississauga',
                'name' => 'Mississauga First Nation',
                'band_number' => 200,
                'treaty' => 'Robinson-Huron Treaty (1850)',
                'reserve' => 'Mississagi River 8',
                'lat' => 46.217,
                'lng' => -83.000,
                'population' => 1511,
                'chief' => 'Brent Niganobe',
                'council' => [
                    'Denise Boyer-Payette', 'Jubilant Sky Cada', 'Crystal Dawn Chiblow', 'Chance Counsell',
                    'Gloria Daybutch', 'Kenneth Macleod', 'Laura Mayer', 'Peyton Pitawanakwat', 'Nancy Whitehead',
                ],
                'website' => 'https://www.mississaugi.com/',
                'anchor' => false,
            ],
            [
                'slug' => 'thessalon',
                'name' => 'Thessalon First Nation',
                'band_number' => 202,
                'treaty' => 'Robinson-Huron Treaty (1850)',
                'reserve' => 'Thessalon 12',
                'lat' => 46.250,
                'lng' => -83.417,
                'population' => 1233,
                'chief' => 'Joseph Wabigwan',
                'council' => [
                    'Lisa Boulrice', 'Roxanne Boulrice', 'Mary Anne Giguere', 'Robert H. Simon', 'Robert S. Simon',
                ],
                'website' => 'https://www.thessalonfirstnation.ca/',
                'anchor' => false,
            ],
            [
                'slug' => 'garden-river',
                'name' => 'Garden River First Nation',
                'band_number' => 199,
                'treaty' => 'Robinson-Huron Treaty (1850)',
                'reserve' => 'Garden River 14 (Gitigaan-ziibi)',
                'lat' => 46.550,
                'lng' => -84.100,
                'population' => 3398,
                'chief' => 'Karen Bell',
                'council' => [
                    'Kari Lynne Barry', 'Darwin Belleau', 'Lee Ann Gamble', 'Kristy Dawn Jones',
                    'Travis Jones', 'Chester Langille', 'Luanne Povey', 'Candice Sim',
                ],
                'website' => 'https://www.gardenriver.org/',
                'anchor' => false,
            ],
            [
                'slug' => 'batchewana',
                'name' => 'Batchewana First Nation',
                'band_number' => 198,
                'treaty' => 'Robinson-Huron Treaty (1850)',
                'reserve' => 'Rankin Location 15D',
                'lat' => 46.550,
                'lng' => -84.300,
                'population' => 3323,
                'chief' => 'Mark McCoy',
                'council' => [
                    'Agnes Bjornaa', 'Luke McCoy', 'Trevor Sayers Sr.', 'Ann Marie Tegosh',
                    'Gary Roach Jr.', 'Brenda Sayers', 'Carol Hermiston', 'Joseph Thomas Sayers',
                ],
                'website' => 'https://www.batchewana.ca/',
                'anchor' => false,
            ],
            [
                'slug' => 'atikameksheng-anishnawbek',
                'name' => 'Atikameksheng Anishnawbek',
                'band_number' => 224,
                'treaty' => 'Robinson-Huron Treaty (1850)',
                'reserve' => 'Whitefish Lake 6',
                'lat' => 46.360,
                'lng' => -81.230,
                'population' => 1618,
                'chief' => 'Craig Nootchtai',
                'council' => [
                    'Lesley MacNeil', 'Vance Nootchtai', 'Arthur Petahtegoose',
                    'Harvey Petahtegoose', 'Jennifer Petahtegoose',
                ],
                'website' => 'https://www.atikamekshenganishnawbek.ca/',
                'anchor' => false,
            ],
        ];
    }

    /**
     * @return array{
     *     slug: string, name: string, band_number: int, treaty: string,
     *     reserve: string, lat: float, lng: float, population: int,
     *     chief: string, council: list<string>, website: string, anchor: bool
     * }|null
     */
    public static function bySlug(string $slug): ?array
    {
        foreach (self::all() as $nation) {
            if ($nation['slug'] === $slug) {
                return $nation;
            }
        }
        return null;
    }

    /**
     * Authoritative ISC First Nation Profile governance page (current chief +
     * council + term dates) for verification — the source of record for #799.
     */
    public static function governanceUrl(int $bandNumber): string
    {
        return 'https://fnp-ppn.aadnc-aandc.gc.ca/fnp/Main/Search/FNGovernance.aspx'
            . '?BAND_NUMBER=' . $bandNumber . '&lang=eng';
    }
}
