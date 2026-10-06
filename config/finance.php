<?php

return [

    /*
    | Banks offered in the staff form's "Bank name" dropdown (Tanzania).
    | Anything not listed can still be typed via the "Other" option.
    */
    'banks' => [
        'Absa Bank Tanzania',
        'Access Bank Tanzania',
        'Akiba Commercial Bank',
        'Amana Bank',
        'Azania Bank',
        'Bank of Africa Tanzania',
        'Bank of Baroda Tanzania',
        'Bank of India Tanzania',
        'Canara Bank Tanzania',
        'China Dasheng Bank',
        'Citibank Tanzania',
        'CRDB Bank',
        'DCB Commercial Bank',
        'Diamond Trust Bank (DTB)',
        'Ecobank Tanzania',
        'Equity Bank Tanzania',
        'Exim Bank Tanzania',
        'Guaranty Trust Bank Tanzania',
        'Habib African Bank',
        'I&M Bank Tanzania',
        'International Commercial Bank (ICB)',
        'KCB Bank Tanzania',
        'Kilimanjaro Co-operative Bank',
        'Letshego Faidika Bank',
        'Maendeleo Bank',
        'Mkombozi Commercial Bank',
        'Mwanga Hakika Bank',
        'NCBA Bank Tanzania',
        'NMB Bank',
        'National Bank of Commerce (NBC)',
        "People's Bank of Zanzibar (PBZ)",
        'Selcom Microfinance Bank',
        'Stanbic Bank Tanzania',
        'Standard Chartered Bank Tanzania',
        'Tanzania Commercial Bank (TCB)',
        'TIB Development Bank',
        'Uchumi Commercial Bank',
        'United Bank for Africa Tanzania',
    ],

    /*
    | Tanzania (mainland) statutory payroll defaults. Rates are percentages.
    | Every rate below can be overridden from Finance → Payroll → Statutory
    | settings (stored in the settings table), so a change in the law does
    | not need a code change. Verify against current TRA / NSSF / WCF rules.
    */
    'statutory' => [
        // Monthly PAYE bands for residents: [band starts above, base tax, rate %].
        'paye_bands' => [
            [0, 0, 0],
            [270000, 0, 8],
            [520000, 20000, 20],
            [760000, 68000, 25],
            [1000000, 128000, 30],
        ],

        'rates' => [
            'nssf_employee' => 10,     // NSSF: 20% of gross, split 10% staff / 10% employer
            'nssf_employer' => 10,
            'sdl' => 3.5,              // Skills Development Levy, employer, on gross emoluments
            'sdl_min_staff' => 10,     // SDL applies only when the employer has this many staff
            'wcf' => 0.5,              // Workers Compensation Fund, employer, on gross
            'leave_provision' => 7.67, // 28 days annual leave a year = 28/365 of basic
            'severance_provision' => 1.92, // 7 days per completed year = 7/365 of basic
            'gratuity_provision' => 0, // Contract gratuity, if contracts promise one (e.g. 25)
        ],

        // When each monthly remittance is due, counted from the payroll month.
        'returns' => [
            'paye' => ['label' => 'PAYE', 'authority' => 'TRA', 'due' => '7th of next month'],
            'sdl' => ['label' => 'SDL', 'authority' => 'TRA', 'due' => '7th of next month'],
            'nssf' => ['label' => 'NSSF', 'authority' => 'NSSF', 'due' => 'End of next month'],
            'wcf' => ['label' => 'WCF', 'authority' => 'WCF', 'due' => 'End of next month'],
        ],
    ],

];
