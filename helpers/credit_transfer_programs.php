<?php
declare(strict_types=1);

/**
 * Static credit-transfer university program catalogues for scholarsyncglobal.
 *
 * These lists are COPIED into MIS (not fetched from any e-learning API).
 * UPAFA programmes were copied from PROGRAM-2025.pdf (assets/docs/upafa-PROGRAM-2025.pdf).
 * USOJ programmes were copied from the University of Saint Joseph Mbarara catalogue.
 */

/** @return array<string, mixed> */
function pcvc_credit_transfer_programs(): array
{
    return [
        // Copied into MIS from UPAFA PROGRAM-2025.pdf (assets/docs/upafa-PROGRAM-2025.pdf).
        'UPAFA' => [
            'Faculty of Administrative Sciences and Computer Technology' => [
                'Management Information Systems', 'General Computing', 'Economy', 'Corporate and Market Finance',
                'Business Administration and Aviation', 'Business Administration in International Marketing',
                'Maintenance – Networks and Telecommunications', 'Marketing & Public Relations', 'Hotel Management and Tourism',
                'Supply Chain Management and Logistics', 'Business Management and Administration', 'Accounting',
                'Economic and Financial Analysis', 'Islamic Finance', 'Home Economics', 'Finance Bank', 'Transport Logistics',
                'Customs Transit', 'Project Planning and Management', 'Finance', 'Information and Communication Technology (ICT)',
                'Computer and Multimedia Networks', 'Data Science', 'Catastrophic Risk Management and Adaptation to Climate Change',
                'Risk Management and Insurance Digital and Customers', 'Portfolio Management', 'Cash Management',
                'Organization Management', 'Economy of Inspiration', 'Economics of Resilience', 'Business Management',
                'Public Administration', 'Audit',
            ],
            'Faculty of Letters and Human Sciences' => [
                'Literature History', 'Civilization and Heritage', 'Legal Sciences', 'Politics and Administration',
                'Jurisprudence', 'Science of Education and Training', 'Translation and Interpretation',
                'Journalism and Communication', 'Sociology and Anthropology', 'Social Work and Community Development',
                'Human Resources Management', 'Philosophy', 'International Development', 'Private and Public Law',
                'International Law', 'Criminology', 'Management and Political Science', 'Theology', 'Islamic Sciences',
                'International Relations and Diplomacy', 'Human and Social Sciences', 'Comparison of Religions',
                'Islamic Philosophy', 'Business Law and Taxation', 'Geography', 'Islamic Theology',
                'Literature and Language (English, Chinese, Russian, Spanish and African Languages)',
            ],
            'Faculty of Applied Natural Sciences' => [
                'Surveying and Geomatics Sciences', 'Geotechnical and Pavement Engineering', 'Civil Engineering',
                'Civil Engineering (Construction Technology, Road and Highway Engineering)',
                'Electrical and Electronic Engineering', 'Water and Sanitation Engineering', 'Geology', 'Forestry Sciences',
                'Agronomy and Animal Husbandry', 'Energy', 'Mining Survey', 'Mining Engineering', 'Oil and Gas Engineering',
                'Architecture', 'Food Science', 'GIS and Urban Planning', 'Agri-business Management', 'Construction Management',
                'Land Management and Administration', 'Mechanical Engineering', 'Mechanical Engineering (Automotive, Manufacturing)',
                'Industrial Engineering', 'Biotechnology',
                'Art and Design Technology (Graphic Design, Fashion Design, Textile and Sewing Technology)',
                'Meter', 'Biodiversity and Conservation', 'Environmental Management', 'Thermal Engineering',
                'Energy and Renewable Energy', 'Real Estate Valuation and Property Management',
            ],
            'Faculty of Medicine and Health Sciences' => [
                'Biomedical Technology', 'General Medicine', 'Health Services Management', 'Public Health', 'Human Nutrition',
                'Epidemiology', 'Forensic Medicine', 'Community Health', 'Clinical Psychology and Guidance',
                'Biomedical Laboratory Sciences', 'Ultrasound', 'Medical Laboratory Sciences', 'Nursing', 'Pharmacy',
                'Pathology', 'Orthopedic Surgery', 'Radiology', 'Gynecology and Obstetrics', 'Mental Health',
                'Clinical Bacteriology',
            ],
        ],

        'DPHU' => [
            'MBA', 'Transport and Logistics Management', 'Human Resource Management', 'Project Management',
            'Economic Development', 'Information and Communications Technology', 'International Criminal & Justice',
            'Land Administration and Management', 'Open Distance Learning', 'Psychology',
            'Administration, Planning and Policy & Studies', 'Curriculum Design and Development', 'Quality Management',
            'Environmental Studies – Health', 'Environmental Studies – Management', 'Environmental Studies – Sciences',
            'Computer Science', 'Information Technology Management', 'Biology', 'Botany', 'Chemistry', 'Physics',
            'Human Nutrition', 'Mathematics', 'Information Communication Technology', 'Social Work', 'Economics',
            'Community Economic Development', 'Tourism Studies', 'Natural Resource Assessment and Management',
            'International Development and Cooperation', 'Humanitarian Action, Cooperation & Development',
            'Governance and Leadership', 'Kiswahili', 'Literature', 'Linguistics', 'Library and Information Management',
            'Monitoring and Evaluation', 'Gender Studies', 'Mass Communication', 'Arts in Literature', 'Geography',
            'History', 'Accounting and Financial Sciences and Techniques', 'Banking and Corporate Finance',
            'Human Resources Management', 'Sales Management and International Marketing',
            'Administration and Management of Organizations', 'Transport Logistics', 'Management Information Systems',
            'Business Communication', 'Private Law', 'Business Law', 'Public Law', 'International Humanitarian Law',
            'International Relations and Diplomacy', 'Banking and Financial Law', 'Insurance Law', 'Corporate Tax Law',
            'Peace Administration', 'International Governance and Sustainable Development',
            'Computer Networks and Telecommunications', 'Civil Engineering – Public Works', 'Electrical Engineering',
            'Mechanical Engineering', 'Rural and Environmental Engineering', 'Livestock and Animal Production',
            'Agronomy – Plant Production', 'Water and Environmental Management/Water and Forestry',
            'Socio-Economy & Rural Economy', 'Sanitary and Environmental Engineering', 'Human Nutrition and Nutrition Policy',
            'Epidemiology of Intervention', 'Health Information Systems Engineering', 'Nursing Sciences',
            'Obstetrical and Gynecological Sciences', 'Mental Health (Psychiatric Care)', 'Community Health Care',
            'Health psychpedagogy', 'Emergency Care', 'Health Care Administration', 'Management of Health and Social Organizations',
            'Hospital Management', 'Reproductive Health', 'Management of Health Projects and Programs',
            'Monitoring & Evaluation of Health Projects and Programs',
        ],

        'IST' => [
            'Advanced Diploma' => [
                'Electrical Engineering', 'Mechanical Engineering', 'Mechanical and Manufacturing Engineering',
                'Aerospace Engineering', 'Civil Engineering and Management', 'Automotive and Power Engineering',
                'Mining Engineering – Geology option', 'Mining Engineering – Metallurgy option', 'Thermal & Energy Engineering',
                'Industrial Engineering', 'Networks & Computer Systems (IT)', 'Agro-industry', 'Agribusiness Engineering',
                'Business Administration and Finance', 'Finance & Accounting', 'Marketing & Business Communication',
                'Banking & Microfinance', 'Medical Laboratory Sciences', 'Nursing', 'Pharmacy',
            ],
            "Bachelor's Programs" => [
                'Electrical Engineering', 'Mechanical Engineering', 'Mechanical and Manufacturing Engineering',
                'Aerospace Engineering', 'Civil Engineering and Management', 'Automotive and Power Engineering',
                'Mining Engineering – Geology option', 'Mining Engineering – Metallurgy option', 'Thermal & Energy Engineering',
                'Industrial Engineering', 'Networks & Computer Systems (IT)', 'Agro-industry', 'Agribusiness Engineering',
                'Business Administration and Finance', 'Finance & Accounting', 'Marketing & Business Communication',
                'Banking & Microfinance', 'Medical Laboratory Sciences', 'Nursing', 'Pharmacy',
            ],
            "Master's Programs" => [
                'Mining Engineering – Mineralurgy option', 'Electrical Engineering', 'Mechanical Engineering',
                'Mechanical and Manufacturing Engineering', 'Aerospace Engineering', 'Civil Engineering and Management',
                'Automotive and Power Engineering', 'Mining Engineering – Geology option', 'Mining Engineering – Metallurgy option',
                'Thermal & Energy Engineering', 'Industrial Engineering', 'Networks & Computer Systems (IT)', 'Agro-industry',
                'Agribusiness Engineering', 'Business Administration and Finance', 'Finance & Accounting',
                'Marketing & Business Communication', 'Banking & Microfinance', 'Medical Laboratory Sciences', 'Nursing', 'Pharmacy',
            ],
        ],

        // Copied into MIS from University of Saint Joseph Mbarara (USOJ) catalogue — static, not live API.
        'USOJ' => [
            "Bachelor's Programs" => [
                'Bachelor of Arts with Education Secondary (BAES)',
                'Bachelor of Business Administration (BBA)',
                'Bachelor of Education Primary (BEP)',
                'Bachelor of Human Resource Management (BHRM)',
                'Bachelor of Mass Communication and Journalism (BMCJ)',
                'Bachelor of Public Administrative Sciences and Management (BPASM)',
                'Bachelor of Science in Accounting and Finance (BSAF)',
                'Bachelor of Science in Information Technology (BsIT)',
                'Bachelor of Science in Procurement and Logistics Management (BSPLM)',
                'Bachelor of Science with Education Secondary (BSES)',
                'Bachelor of Social Work and Social Transformation (BSWST)',
            ],
            "Master's Programs" => [
                'Master of Arts with Education Secondary (MAES)',
                'Master of Business Administration (MBA)',
                'Master of Education Primary (MEP)',
                'Master of Human Resource Management (MHRM)',
                'Master of Mass Communication and Journalism (MMCJ)',
                'Master of Public Administrative Sciences and Management (MPASM)',
                'Master of Science in Accounting and Finance (MSAF)',
                'Master of Science in Information Technology (MsIT)',
                'Master of Science in Procurement and Logistics Management (MSPLM)',
                'Master of Science with Education Secondary (MSES)',
                'Master of Social Work and Social Transformation (MSWST)',
            ],
            'PhD Programs' => [
                'PhD in Business Administration',
                'PhD in Education',
                'PhD in Human Resource Management',
                'PhD in Information Technology',
                'PhD in Mass Communication and Journalism',
                'PhD in Public Administrative Sciences and Management',
                'PhD in Accounting and Finance',
                'PhD in Procurement and Logistics Management',
                'PhD in Social Work and Social Transformation',
            ],
            'Diploma Programs' => [
                'Diploma in Business Administration',
                'Diploma in Education Primary',
                'Diploma in Information Technology',
                'Diploma in Journalism and Mass Communication',
                'Diploma in Procurement and Logistics Management',
            ],
            'Short Courses' => [
                'Computer Applications',
                'Soft Skills',
            ],
        ],
    ];
}

/** Flat list for student portal datalist (IST / USOJ groups flattened). */
function pcvc_credit_transfer_programs_flat(): array
{
    $out = [];
    foreach (pcvc_credit_transfer_programs() as $code => $data) {
        if ($data === [] || !is_array($data)) {
            $out[$code] = [];
            continue;
        }
        $first = reset($data);
        if (is_array($first)) {
            $flat = [];
            foreach ($data as $group) {
                if (is_array($group)) {
                    foreach ($group as $name) {
                        $flat[] = $name;
                    }
                }
            }
            $out[$code] = array_values(array_unique($flat));
        } else {
            $out[$code] = array_values($data);
        }
    }

    return $out;
}

/** @return list<string> */
function pcvc_credit_transfer_university_codes(): array
{
    return array_keys(pcvc_credit_transfer_programs());
}

/** Public PDF catalogue for a credit-transfer university, if one is bundled. */
function pcvc_credit_transfer_catalogue_path(string $code): ?string
{
    $map = [
        'UPAFA' => 'assets/docs/upafa-PROGRAM-2025.pdf',
    ];

    return $map[$code] ?? null;
}
