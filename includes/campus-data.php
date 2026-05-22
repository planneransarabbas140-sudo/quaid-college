<?php
/**
 * Per-campus content for campus page template.
 * Requires includes/site-stats.php loaded first.
 */

$campus_profiles = [
    'rajanpur' => [
        'key'           => 'rajanpur',
        'campus_number' => '03',
        'meta_desc'     => 'Misbah Campus Rajanpur — flagship campus of Quaid-e-Azam Group of Colleges. Programs, leadership, admissions, and contact.',
        'video'         => 'assets/videos/rajanpur-campus.mp4',
        'video_poster'  => 'assets/images/image1.png',
        'map_embed'     => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d55633.0!2d70.3290!3d29.1040!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x393b5b5b5b5b5b5b%3A0x0!2sRajanpur%2C+Punjab%2C+Pakistan!5e0!3m2!1sen!2spk!4v1',
        'hero_pills'    => ['FA Arts', 'FSc Pre-Medical', 'FSc Pre-Engineering', 'ICS Computer Science', 'BSCS', 'BSIT', '+8 more'],
        'welcome_text'  => 'Misbah Campus is the flagship Rajanpur campus of Quaid-e-Azam Group of Colleges, serving thousands of students with a disciplined academic culture. The campus combines intermediate excellence, degree programs, and high-demand technical training.',
        'highlights'    => [
            'Flagship campus with modern labs and digital classrooms',
            'Strong BISE DG Khan and IUB affiliated pathways',
            'Dedicated mentorship for board and university exams',
        ],
        'show_matric_filter' => false,
        'leaders' => [
            [
                'image' => 'assets/images/saif shb (1).png',
                'badge' => 'FOUNDER & CHAIRMAN',
                'badge_class' => '',
                'name'  => 'Ch. Saif Ullah',
                'role'  => 'Quaid-e-Azam Group of Colleges',
                'quote' => 'Excellence in education is our commitment to every student.',
                'photo_class' => '',
            ],
            [
                'image' => 'assets/images/zafar shb (2).png',
                'badge' => 'CAMPUS PRINCIPAL',
                'badge_class' => 'teal',
                'name'  => 'Mr. Zafar Iqbal',
                'role'  => 'Misbah Campus',
                'quote' => 'We nurture talent and build future leaders of Pakistan.',
                'photo_class' => 'teal',
            ],
        ],
        'programs' => [
            ['cat'=>'intermediate','icon'=>'📚','name'=>'FA Arts','duration'=>'2 Years'],
            ['cat'=>'intermediate','icon'=>'🩺','name'=>'FSc Pre-Medical','duration'=>'2 Years'],
            ['cat'=>'intermediate','icon'=>'⚙️','name'=>'FSc Pre-Engineering','duration'=>'2 Years'],
            ['cat'=>'intermediate','icon'=>'💻','name'=>'ICS Computer Science','duration'=>'2 Years'],
            ['cat'=>'degree','icon'=>'🖥️','name'=>'BSCS','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'🌐','name'=>'BSIT','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'🧬','name'=>'BS Biology','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'📐','name'=>'BS Mathematics','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'🎓','name'=>'B.Ed','duration'=>'2 Years'],
            ['cat'=>'degree','icon'=>'🔭','name'=>'ADP Science','duration'=>'2 Years'],
            ['cat'=>'degree','icon'=>'📖','name'=>'ADP Arts','duration'=>'2 Years'],
            ['cat'=>'navttc','icon'=>'👨‍💻','name'=>'Web Development','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'📣','name'=>'Digital Marketing','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'🎨','name'=>'Graphic Designing','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'⌨️','name'=>'Computer Office Management','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'📱','name'=>'Mobile Application Development','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'🤖','name'=>'Artificial Intelligence','duration'=>'6 Months'],
        ],
    ],
    'fazilpur' => [
        'key'           => 'fazilpur',
        'campus_number' => '01',
        'meta_desc'     => 'Hamid Campus Fazilpur — Quaid-e-Azam Group of Colleges. Intermediate, degree, matric, and NAVTTC programs.',
        'video'         => 'assets/videos/fazilpur-campus.mp4',
        'video_poster'  => 'assets/images/image2.png',
        'map_embed'     => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3474.0!2d70.5290!3d29.3190!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sFazilpur%2C+Punjab%2C+Pakistan!5e0!3m2!1sen!2spk!4v1',
        'hero_pills'    => ['9th Grade Science', 'FSc Pre-Medical', 'FSc Pre-Engineering', 'BSCS', 'BSIT', 'Web Development', '+11 more'],
        'welcome_text'  => 'Hamid Campus brings Quaid-e-Azam Group of Colleges\' academic tradition to Fazilpur with a focused, student-centered environment. Our programs blend board excellence, degree readiness, and practical skills for modern careers.',
        'highlights'    => [
            'Matric and intermediate programs under one roof',
            'Experienced intermediate section leadership',
            'NAVTTC skill courses for job-ready graduates',
        ],
        'show_matric_filter' => true,
        'leaders' => [
            [
                'image' => 'assets/images/saif shb (1).png',
                'badge' => 'FOUNDER & CHAIRMAN',
                'badge_class' => '',
                'name'  => 'Ch. Saif Ullah',
                'role'  => 'Quaid-e-Azam Group of Colleges',
                'quote' => 'Excellence in education is our commitment to every student.',
                'photo_class' => '',
            ],
            [
                'image' => 'assets/images/mohsin.png',
                'badge' => 'CAMPUS PRINCIPAL',
                'badge_class' => 'teal',
                'name'  => 'Mr. Mohsin Abbas',
                'role'  => 'Hamid Campus',
                'quote' => 'We nurture talent and build future leaders of Pakistan.',
                'photo_class' => 'teal',
            ],
            [
                'image' => 'assets/images/abid.png',
                'badge' => 'HEAD OF INTERMEDIATE',
                'badge_class' => 'navy',
                'name'  => 'Mr. Abid Hussain',
                'role'  => 'Campus Partner, Hamid Campus Fazilpur',
                'quote' => 'These two years of Intermediate define the entire direction of a student\'s life.',
                'photo_class' => '',
            ],
        ],
        'programs' => [
            ['cat'=>'matric','icon'=>'🔬','name'=>'9th Grade Science','duration'=>'1 Year'],
            ['cat'=>'matric','icon'=>'🧪','name'=>'10th Grade Science','duration'=>'1 Year'],
            ['cat'=>'intermediate','icon'=>'📚','name'=>'FA Arts','duration'=>'2 Years'],
            ['cat'=>'intermediate','icon'=>'🩺','name'=>'FSc Pre-Medical','duration'=>'2 Years'],
            ['cat'=>'intermediate','icon'=>'⚙️','name'=>'FSc Pre-Engineering','duration'=>'2 Years'],
            ['cat'=>'intermediate','icon'=>'💻','name'=>'ICS Computer Science','duration'=>'2 Years'],
            ['cat'=>'degree','icon'=>'🖥️','name'=>'BSCS','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'🌐','name'=>'BSIT','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'🧬','name'=>'BS Biology','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'📐','name'=>'BS Mathematics','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'🎓','name'=>'B.Ed','duration'=>'2 Years'],
            ['cat'=>'degree','icon'=>'🔭','name'=>'ADP Science','duration'=>'2 Years'],
            ['cat'=>'degree','icon'=>'📖','name'=>'ADP Arts','duration'=>'2 Years'],
            ['cat'=>'navttc','icon'=>'👨‍💻','name'=>'Web Development','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'📣','name'=>'Digital Marketing','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'🎨','name'=>'Graphic Designing','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'🧩','name'=>'UI/UX Design','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'⌨️','name'=>'Computer Office Management','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'📱','name'=>'Mobile Application Development','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'🤖','name'=>'Artificial Intelligence','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'✨','name'=>'Beautician & Parlor','duration'=>'6 Months'],
        ],
    ],
    'kotmithan' => [
        'key'           => 'kotmithan',
        'campus_number' => '02',
        'meta_desc'     => 'Abul Rehman Campus Kot Mithan — quality intermediate, degree, and NAVTTC programs close to home.',
        'video'         => 'assets/videos/kotmithan-campus.mp4',
        'video_poster'  => 'assets/images/image3.png',
        'map_embed'     => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3474.0!2d70.4500!3d29.2000!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sKot%20Mithan%2C+Punjab%2C+Pakistan!5e0!3m2!1sen!2spk!4v1',
        'hero_pills'    => ['FA Arts', 'FSc Pre-Medical', 'ICS Computer Science', 'BSCS', 'Web Development', '+11 more'],
        'welcome_text'  => 'Abul Rehman Campus serves Kot Mithan with a modern academic environment built for confidence, discipline, and measurable progress. Students access intermediate, degree, and NAVTTC programs close to home.',
        'highlights'    => [
            'Community-focused campus in Kot Mithan',
            'Balanced intermediate and degree offerings',
            'Practical NAVTTC certifications available',
        ],
        'show_matric_filter' => false,
        'leaders' => [
            [
                'image' => 'assets/images/saif shb (1).png',
                'badge' => 'FOUNDER & CHAIRMAN',
                'badge_class' => '',
                'name'  => 'Ch. Saif Ullah',
                'role'  => 'Quaid-e-Azam Group of Colleges',
                'quote' => 'Excellence in education is our commitment to every student.',
                'photo_class' => '',
            ],
            [
                'image' => 'assets/images/asif.png',
                'badge' => 'CAMPUS PRINCIPAL',
                'badge_class' => 'teal',
                'name'  => 'Mr. Asif Hussain',
                'role'  => 'Abul Rehman Campus',
                'quote' => 'We nurture talent and build future leaders of Pakistan.',
                'photo_class' => 'teal',
            ],
        ],
        'programs' => [
            ['cat'=>'intermediate','icon'=>'📚','name'=>'FA Arts','duration'=>'2 Years'],
            ['cat'=>'intermediate','icon'=>'🩺','name'=>'FSc Pre-Medical','duration'=>'2 Years'],
            ['cat'=>'intermediate','icon'=>'⚙️','name'=>'FSc Pre-Engineering','duration'=>'2 Years'],
            ['cat'=>'intermediate','icon'=>'💻','name'=>'ICS Computer Science','duration'=>'2 Years'],
            ['cat'=>'degree','icon'=>'🖥️','name'=>'BSCS','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'🌐','name'=>'BSIT','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'🧬','name'=>'BS Biology','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'📐','name'=>'BS Mathematics','duration'=>'4 Years'],
            ['cat'=>'degree','icon'=>'🎓','name'=>'B.Ed','duration'=>'2 Years'],
            ['cat'=>'degree','icon'=>'🔭','name'=>'ADP Science','duration'=>'2 Years'],
            ['cat'=>'degree','icon'=>'📖','name'=>'ADP Arts','duration'=>'2 Years'],
            ['cat'=>'navttc','icon'=>'👨‍💻','name'=>'Web Development','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'📣','name'=>'Digital Marketing','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'🎨','name'=>'Graphic Designing','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'⌨️','name'=>'Computer Office Management','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'📱','name'=>'Mobile Application Development','duration'=>'6 Months'],
            ['cat'=>'navttc','icon'=>'🤖','name'=>'Artificial Intelligence','duration'=>'6 Months'],
        ],
    ],
];

if (!function_exists('qgc_get_campus')) {
    /**
     * @return array Merged site stats + campus profile (programs count string preserved)
     */
    function qgc_get_campus(string $key): array {
        global $campus_stats, $campus_profiles;
        if (!isset($campus_stats[$key], $campus_profiles[$key])) {
            $key = 'rajanpur';
        }
        $stats = $campus_stats[$key];
        $profile = $campus_profiles[$key];
        $program_list = $profile['programs'];
        unset($profile['programs']);
        return array_merge($stats, $profile, ['program_list' => $program_list]);
    }
}

if (!function_exists('qgc_program_filter_counts')) {
    function qgc_program_filter_counts(array $programs): array {
        $counts = ['all' => count($programs), 'matric' => 0, 'intermediate' => 0, 'degree' => 0, 'navttc' => 0];
        foreach ($programs as $p) {
            if (isset($counts[$p['cat']])) {
                $counts[$p['cat']]++;
            }
        }
        return $counts;
    }
}

if (!function_exists('qgc_tel_href')) {
    function qgc_tel_href(string $phone): string {
        return 'tel:' . preg_replace('/[^\d+]/', '', $phone);
    }
}
