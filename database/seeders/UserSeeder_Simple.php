<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder_Simple extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@volvicon.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@volvicon.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole('admin');

        // Create editor users
        $editors = [
            ['name' => 'Dr. Sarah Johnson', 'email' => 'sarah.johnson@university.edu', 'role' => 'editor-in-chief'],
            ['name' => 'Prof. Michael Chen', 'email' => 'michael.chen@mit.edu', 'role' => 'associate-editor'],
            ['name' => 'Dr. Emily Rodriguez', 'email' => 'emily.rodriguez@harvard.edu', 'role' => 'managing-editor'],
        ];

        foreach ($editors as $editorData) {
            $editor = User::updateOrCreate(
                ['email' => $editorData['email']],
                [
                    'name' => $editorData['name'],
                    'email' => $editorData['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);
            $editor->assignRole($editorData['role']);
        }

        // Create reviewer users
        $reviewers = [
            ['name' => 'Dr. James Wilson', 'email' => 'james.wilson@stanford.edu'],
            ['name' => 'Prof. Lisa Anderson', 'email' => 'lisa.anderson@berkeley.edu'],
            ['name' => 'Dr. Robert Kumar', 'email' => 'robert.kumar@oxford.ac.uk'],
            ['name' => 'Dr. Maria Garcia', 'email' => 'maria.garcia@cambridge.ac.uk'],
            ['name' => 'Prof. David Kim', 'email' => 'david.kim@seoul.ac.kr'],
        ];

        foreach ($reviewers as $reviewerData) {
            $reviewer = User::updateOrCreate(
                ['email' => $reviewerData['email']],
                [
                    'name' => $reviewerData['name'],
                    'email' => $reviewerData['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);
            $reviewer->assignRole('reviewer');
        }

        // Create author users
        $authors = [
            ['name' => 'Dr. Jennifer Thompson', 'email' => 'jennifer.thompson@yale.edu'],
            ['name' => 'Dr. Ahmed Hassan', 'email' => 'ahmed.hassan@cairo.edu.eg'],
            ['name' => 'Prof. Anna Kowalski', 'email' => 'anna.kowalski@warsaw.edu.pl'],
            ['name' => 'Dr. Carlos Silva', 'email' => 'carlos.silva@usp.br'],
            ['name' => 'Dr. Yuki Tanaka', 'email' => 'yuki.tanaka@tokyo.ac.jp'],
            ['name' => 'Dr. Sophie Laurent', 'email' => 'sophie.laurent@sorbonne.fr'],
        ];

        foreach ($authors as $authorData) {
            $author = User::updateOrCreate(
                ['email' => $authorData['email']],
                [
                    'name' => $authorData['name'],
                    'email' => $authorData['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);
            $author->assignRole('author');
        }

        // Create some users with multiple roles
        $editorAuthor = User::updateOrCreate(
            ['email' => 'alex.morgan@columbia.edu'],
            [
                'name' => 'Dr. Alex Morgan',
                'email' => 'alex.morgan@columbia.edu',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        $editorAuthor->assignRole(['associate-editor', 'author']);

        $reviewerAuthor = User::updateOrCreate(
            ['email' => 'sam.parker@princeton.edu'],
            [
                'name' => 'Dr. Sam Parker',
                'email' => 'sam.parker@princeton.edu',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        $reviewerAuthor->assignRole(['reviewer', 'author']);

        $this->command->info('Users with roles seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- 1 admin (admin role)');
        $this->command->info('- 1 editor-in-chief');
        $this->command->info('- 1 associate-editor');
        $this->command->info('- 1 managing-editor');
        $this->command->info('- 5 reviewers');
        $this->command->info('- 6 authors');
        $this->command->info('- 1 associate-editor + author');
        $this->command->info('- 1 reviewer + author');
        $this->command->info('Total: 17 users with appropriate roles');
        $this->command->info('Admin login: admin@volvicon.com / password');
    }
}
