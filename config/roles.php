<?php

/**
 * Roles shipped with the system: display labels and the starting permission set of each.
 *
 * The permission sets are only a starting point. RoleCatalogSeeder applies them to a role the first time
 * it creates it and never again, so whatever the admin later changes in the "Roles & permissions"
 * screen is kept. Roles are ordinary spatie roles; an admin can also add their own.
 */
return [
    // The three roles that are not building-staff: the software owner, and the residents.
    'resident_roles' => ['flat_owner', 'tenant'],

    // super_admin bypasses every permission check (Gate::before), so it is never edited.
    'locked_roles' => ['super_admin'],

    // Permissions the admin role must always keep, or nobody could ever fix a wrong tick-box again.
    'admin_must_keep' => ['user.view', 'user.manage', 'role.manage'],

    'labels' => [
        'super_admin' => ['bn' => 'সুপার অ্যাডমিন', 'en' => 'Super Admin'],
        'admin' => ['bn' => 'অ্যাডমিন', 'en' => 'Admin'],
        'manager' => ['bn' => 'বিল্ডিং ম্যানেজার', 'en' => 'Building Manager'],
        'committee_president' => ['bn' => 'সভাপতি', 'en' => 'Committee President'],
        'committee_secretary' => ['bn' => 'সম্পাদক', 'en' => 'Committee Secretary'],
        'committee_treasurer' => ['bn' => 'কোষাধ্যক্ষ', 'en' => 'Committee Treasurer'],
        'committee_member' => ['bn' => 'কমিটি সদস্য', 'en' => 'Committee Member'],
        'flat_owner' => ['bn' => 'ফ্ল্যাট মালিক', 'en' => 'Flat Owner'],
        'tenant' => ['bn' => 'ভাড়াটিয়া', 'en' => 'Tenant'],
    ],

    // Starting permissions for roles created by the seeder (admin gets everything, see the seeder).
    'defaults' => [
        'manager' => [
            'building.view', 'flat.view', 'person.view', 'document.view', 'gas.manage', 'gas.view',
            'payment.create', 'payment.view', 'expense.create', 'expense.view', 'complaint.manage', 'complaint.view',
            'hall.view', 'hall.request', 'hall.payments', 'notice.view',
        ],
        // chairs the committee: sees everything, signs off the money, runs meetings
        'committee_president' => [
            'building.view', 'flat.view', 'person.view', 'document.view', 'staff.view', 'billing.view', 'gas.view',
            'payment.view', 'payment.approve', 'expense.view', 'expense.approve', 'accounting.view', 'due.view', 'asset.view',
            'meeting.view', 'meeting.manage', 'complaint.view', 'complaint.manage', 'notice.view', 'notice.manage',
            'hall.view', 'hall.request', 'hall.approve', 'hall.manage', 'user.view',
        ],
        // day-to-day running: notices, meetings, complaints and hall bookings
        'committee_secretary' => [
            'building.view', 'flat.view', 'person.view', 'document.view', 'complaint.view', 'complaint.manage',
            'meeting.view', 'meeting.manage', 'notice.view', 'notice.manage',
            'hall.view', 'hall.request', 'hall.approve',
        ],
        // records and checks the money; approval of payments/expenses stays with the president/admin
        'committee_treasurer' => [
            'building.view', 'flat.view', 'billing.view', 'gas.view', 'payment.view', 'payment.create',
            'expense.view', 'expense.create', 'accounting.view', 'due.view', 'asset.view', 'notice.view',
            'hall.view', 'hall.payments',
        ],
        // read-only: sees what the committee decides on
        'committee_member' => [
            'building.view', 'flat.view', 'billing.view', 'payment.view', 'expense.view', 'accounting.view',
            'due.view', 'asset.view', 'meeting.view', 'complaint.view', 'notice.view', 'hall.view',
        ],
    ],

    // Permission names are "<module>.<action>"; these turn them into readable labels on the screen.
    'groups' => [
        'building' => ['bn' => 'বিল্ডিং', 'en' => 'Building'],
        'flat' => ['bn' => 'ফ্ল্যাট', 'en' => 'Flats'],
        'person' => ['bn' => 'ব্যক্তি', 'en' => 'People'],
        'tenant' => ['bn' => 'ভাড়াটিয়া', 'en' => 'Tenants'],
        'document' => ['bn' => 'ডকুমেন্ট', 'en' => 'Documents'],
        'staff' => ['bn' => 'স্টাফ', 'en' => 'Staff'],
        'billing' => ['bn' => 'বিলিং', 'en' => 'Billing'],
        'gas' => ['bn' => 'গ্যাস', 'en' => 'Gas'],
        'payment' => ['bn' => 'পেমেন্ট', 'en' => 'Payments'],
        'expense' => ['bn' => 'খরচ', 'en' => 'Expenses'],
        'accounting' => ['bn' => 'হিসাব ও রিপোর্ট', 'en' => 'Accounts & reports'],
        'due' => ['bn' => 'নির্মাণ বকেয়া', 'en' => 'Construction dues'],
        'asset' => ['bn' => 'সম্পদ', 'en' => 'Assets'],
        'meeting' => ['bn' => 'মিটিং', 'en' => 'Meetings'],
        'complaint' => ['bn' => 'অভিযোগ', 'en' => 'Complaints'],
        'notice' => ['bn' => 'নোটিশ', 'en' => 'Notices'],
        'hall' => ['bn' => 'কমিউনিটি হল', 'en' => 'Community hall'],
        'parking' => ['bn' => 'পার্কিং', 'en' => 'Parking'],
        'user' => ['bn' => 'ইউজার', 'en' => 'Users'],
        'role' => ['bn' => 'রোল', 'en' => 'Roles'],
    ],
    'actions' => [
        'view' => ['bn' => 'দেখা', 'en' => 'View'],
        'create' => ['bn' => 'তৈরি', 'en' => 'Create'],
        'update' => ['bn' => 'সম্পাদনা', 'en' => 'Edit'],
        'delete' => ['bn' => 'মোছা', 'en' => 'Delete'],
        'manage' => ['bn' => 'পরিচালনা', 'en' => 'Manage'],
        'approve' => ['bn' => 'অনুমোদন', 'en' => 'Approve'],
        'request' => ['bn' => 'অনুরোধ/বুকিং নেওয়া', 'en' => 'Request / take booking'],
        'payments' => ['bn' => 'টাকা নেওয়া/ফেরত', 'en' => 'Take / refund money'],
    ],
];
