<?php
/**
 * Shared statistics for public-facing QGC pages.
 * Update values here once — used across index, about, programs, and campus pages.
 */

$established_year = 2015;
$years_active       = max(1, (int)date('Y') - $established_year);
$years_active_display = $years_active . '+';

$site_stats = [
    'network_students' => '4,050+',
    'network_faculty'  => '103',
    'campuses'         => 3,
    'pass_rate'        => '97%',
    'programs_network' => '25+',
];

$site_phone = '+923338879961';
$site_email = 'info@qgc.edu.pk';

$campus_stats = [
    'rajanpur' => [
        'name'      => 'Misbah Campus',
        'city'      => 'Rajanpur',
        'students'  => '2,000+',
        'faculty'   => '60+',
        'programs'  => '25+',
        'established' => '2015',
        'badge'     => 'MAIN CAMPUS',
        'url'       => 'campus-rajanpur.php',
        'image'     => 'assets/images/image1.png',
        'address'   => 'College Road, Rajanpur, Punjab',
        'email'     => 'misbah@qgc.edu.pk',
        'phone'     => $site_phone,
    ],
    'fazilpur' => [
        'name'      => 'Hamid Campus',
        'city'      => 'Fazilpur',
        'students'  => '1,200+',
        'faculty'   => '28',
        'programs'  => '15+',
        'established' => '2019',
        'badge'     => 'SUB CAMPUS',
        'url'       => 'campus-fazilpur.php',
        'image'     => 'assets/images/image2.png',
        'address'   => 'Main Bazar, Fazilpur, Punjab',
        'email'     => 'hamid@qgc.edu.pk',
        'phone'     => $site_phone,
    ],
    'kotmithan' => [
        'name'      => 'Abul Rehman Campus',
        'city'      => 'Kot Mithan',
        'students'  => '850+',
        'faculty'   => '25',
        'programs'  => '12+',
        'established' => '2020',
        'badge'     => 'SUB CAMPUS',
        'url'       => 'campus-kotmithan.php',
        'image'     => 'assets/images/image3.png',
        'address'   => 'Kot Mithan, Punjab',
        'email'     => 'abdulrehman@qgc.edu.pk',
        'phone'     => $site_phone,
    ],
];

$total_students   = $site_stats['network_students'];
$total_faculty    = $site_stats['network_faculty'];
$total_campuses   = $site_stats['campuses'];
$programs_offered = $site_stats['programs_network'];
$pass_rate        = $site_stats['pass_rate'];

if (!function_exists('program_apply_url')) {
    function program_apply_url(string $name): string {
        return 'modules/admissions/apply.php?program=' . urlencode($name);
    }
}

if (!function_exists('qgc_campus_from_request')) {
    function qgc_campus_from_request(): string {
        $campus = isset($_GET['campus']) ? (string)$_GET['campus'] : 'rajanpur';
        return array_key_exists($campus, $GLOBALS['campus_stats']) ? $campus : 'rajanpur';
    }
}

if (!function_exists('qgc_apply_campus_vars')) {
    /** @return array{name:string,city:string,students:string,faculty:string,programs:string,established:string,badge:string,url:string} */
    function qgc_apply_campus_vars(string $campus_key): array {
        global $campus_stats;
        $c = $campus_stats[$campus_key] ?? $campus_stats['rajanpur'];
        return $c;
    }
}
