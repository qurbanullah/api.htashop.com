<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Profile;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create profiles for existing users
        $users = User::all();

        foreach ($users as $user) {
            $userRoles = $user->getRoleNames()->toArray();

            // Skip admin users without academic roles
            if ($user->hasRole('admin') && !$user->hasAnyRole(['author', 'reviewer', 'editor-in-chief', 'associate-editor', 'managing-editor'])) {
                continue;
            }

            $profileData = $this->getProfileDataForUser($user, $userRoles);

            // Create academic profile using polymorphic relationship
            $user->updateOrCreateProfile($profileData, Profile::TYPE_ACADEMIC);
        }

        $this->command->info('Profiles seeded successfully!');
        $this->command->info('Created academic profiles for all academic users');
    }

    private function getProfileDataForUser(User $user, array $roles): array
    {
        // Base profile data
        $names = $this->parseUserName($user->name);

        $baseData = [
            'first_name' => $names['first'],
            'last_name' => $names['last'],
            'middle_name' => $names['middle'],
            'title' => $this->getAcademicTitle($user->name),
            'orcid' => $this->generateOrcid(),
            'scopus_id' => 'SCOP-' . str_pad(rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'google_scholar_id' => 'GS-' . bin2hex(random_bytes(8)),
            'researcher_id' => 'RES-' . bin2hex(random_bytes(6)),
            'primary_affiliation' => $this->getRandomUniversity(),
            'department' => $this->getRandomDepartment(),
            'institution' => $this->getRandomUniversity(),
            'position' => $this->getPosition($roles),
            'country' => 'US',
            'city' => $this->getRandomCity(),
            'email' => $user->email,
            'bio' => $this->generateBio($roles),
            'research_areas' => $this->getResearchAreas($roles),
            'expertise_keywords' => $this->getExpertiseKeywords($roles),
            'languages' => ['English', ...$this->getRandomLanguages()],
            'career_stage' => $this->getCareerStage($roles),
            'review_expertise' => in_array('reviewer', $roles) ? $this->getReviewExpertise() : null,
            'review_preferences' => in_array('reviewer', $roles) ? $this->getReviewPreferences() : null,
            'available_for_review' => in_array('reviewer', $roles) || in_array('associate-editor', $roles),
            'max_reviews_per_year' => in_array('reviewer', $roles) ? rand(5, 20) : null,
            'preferred_review_topics' => in_array('reviewer', $roles) ? $this->getResearchAreas($roles) : null,
            'editorial_experience' => $this->hasEditorialRole($roles) ? $this->getEditorialExperience() : null,
            'journal_associations' => $this->hasEditorialRole($roles) ? $this->getJournalAssociations() : null,
            'available_for_editorial' => $this->hasEditorialRole($roles),
            'editorial_expertise' => $this->hasEditorialRole($roles) ? $this->getResearchAreas($roles) : null,
            'publication_history' => in_array('author', $roles) ? $this->getPublicationHistory() : null,
            'h_index' => in_array('author', $roles) ? rand(5, 50) : null,
            'citation_count' => in_array('author', $roles) ? rand(100, 5000) : null,
            'preferred_manuscript_types' => in_array('author', $roles) ? $this->getPreferredManuscriptTypes() : null,
            'social_links' => $this->getSocialLinks(),
            'professional_memberships' => $this->getProfessionalMemberships(),
            'is_verified' => rand(0, 100) > 30, // 70% verified
            'verified_at' => rand(0, 100) > 30 ? now()->subDays(rand(1, 365)) : null,
            'is_active' => true,
            'is_public' => rand(0, 100) > 20, // 80% public
            'preferred_language' => 'en',
        ];

        return $baseData;
    }

    private function parseUserName(string $fullName): array
    {
        // Remove academic titles
        $name = preg_replace('/^(Dr\.|Prof\.|Mr\.|Ms\.|Mrs\.)\s+/i', '', $fullName);

        $parts = explode(' ', trim($name));

        if (count($parts) === 1) {
            return ['first' => $parts[0], 'last' => 'User', 'middle' => null];
        } elseif (count($parts) === 2) {
            return ['first' => $parts[0], 'last' => $parts[1], 'middle' => null];
        } else {
            return [
                'first' => $parts[0],
                'middle' => implode(' ', array_slice($parts, 1, -1)),
                'last' => end($parts)
            ];
        }
    }

    private function getAcademicTitle(string $name): ?string
    {
        $titles = ['Dr.', 'Prof.', 'Prof. Dr.', 'Assoc. Prof.', 'Asst. Prof.'];

        if (preg_match('/^(Dr\.|Prof\.|Mr\.|Ms\.|Mrs\.)/i', $name, $matches)) {
            return $matches[1];
        }

        // 60% chance of having a title
        return rand(0, 100) > 40 ? $titles[array_rand($titles)] : null;
    }

    private function generateOrcid(): string
    {
        return sprintf(
            '%04d-%04d-%04d-%04d',
            rand(0, 9999),
            rand(0, 9999),
            rand(0, 9999),
            rand(0, 9999)
        );
    }

    private function getRandomUniversity(): string
    {
        $universities = [
            'Massachusetts Institute of Technology',
            'Stanford University',
            'Harvard University',
            'University of Cambridge',
            'University of Oxford',
            'California Institute of Technology',
            'ETH Zurich',
            'Imperial College London',
            'University of Chicago',
            'Princeton University',
            'National University of Singapore',
            'Nanyang Technological University',
            'Peking University',
            'Tsinghua University',
            'University of Pennsylvania',
            'Yale University',
            'Cornell University',
            'Columbia University',
            'University of Edinburgh',
            'University of Michigan',
        ];

        return $universities[array_rand($universities)];
    }

    private function getRandomDepartment(): string
    {
        $departments = [
            'Computer Science',
            'Biology',
            'Chemistry',
            'Physics',
            'Mathematics',
            'Engineering',
            'Medicine',
            'Psychology',
            'Economics',
            'Sociology',
            'Environmental Science',
            'Neuroscience',
            'Biomedical Engineering',
            'Materials Science',
            'Electrical Engineering',
            'Mechanical Engineering',
            'Civil Engineering',
            'Chemical Engineering',
            'Data Science',
            'Artificial Intelligence',
        ];

        return $departments[array_rand($departments)];
    }

    private function getRandomCity(): string
    {
        $cities = [
            'Boston', 'Cambridge', 'San Francisco', 'New York', 'Chicago',
            'Los Angeles', 'Seattle', 'Austin', 'Philadelphia', 'San Diego',
            'Houston', 'Phoenix', 'Denver', 'Atlanta', 'Miami'
        ];

        return $cities[array_rand($cities)];
    }

    private function getPosition(array $roles): string
    {
        if (in_array('editor-in-chief', $roles)) {
            return 'Professor and Editor-in-Chief';
        }

        if (in_array('associate-editor', $roles) || in_array('managing-editor', $roles)) {
            return 'Associate Professor';
        }

        if (in_array('reviewer', $roles)) {
            return ['Assistant Professor', 'Associate Professor', 'Senior Researcher'][rand(0, 2)];
        }

        if (in_array('author', $roles)) {
            return ['PhD Student', 'Postdoctoral Researcher', 'Research Fellow', 'Assistant Professor'][rand(0, 3)];
        }

        return 'Researcher';
    }

    private function generateBio(array $roles): string
    {
        $bios = [
            "Experienced researcher with a focus on cutting-edge developments in the field. Published extensively in peer-reviewed journals.",
            "Dedicated scientist committed to advancing knowledge through rigorous research and collaboration.",
            "Interdisciplinary researcher bridging theory and practice. Passionate about mentoring the next generation of scientists.",
            "Award-winning researcher with expertise spanning multiple domains. Active contributor to the scientific community.",
            "Innovative thinker exploring novel approaches to complex problems. Extensive publication record and conference presentations.",
        ];

        return $bios[array_rand($bios)];
    }

    private function getResearchAreas(array $roles): array
    {
        $allAreas = [
            'Artificial Intelligence',
            'Machine Learning',
            'Data Science',
            'Bioinformatics',
            'Computational Biology',
            'Neuroscience',
            'Climate Science',
            'Materials Science',
            'Quantum Computing',
            'Renewable Energy',
            'Genetics',
            'Molecular Biology',
            'Ecology',
            'Environmental Science',
            'Social Psychology',
            'Cognitive Science',
            'Economics',
            'Public Health',
        ];

        $count = rand(2, 5);
        return array_slice($allAreas, 0, $count);
    }

    private function getExpertiseKeywords(array $roles): array
    {
        $keywords = [
            'Deep Learning', 'Neural Networks', 'Computer Vision', 'NLP',
            'Genomics', 'Proteomics', 'Cell Biology', 'Immunology',
            'Climate Modeling', 'Sustainability', 'Conservation',
            'Statistical Analysis', 'Bayesian Methods', 'Time Series',
            'Policy Analysis', 'Health Economics', 'Epidemiology'
        ];

        return array_slice($keywords, 0, rand(3, 7));
    }

    private function getRandomLanguages(): array
    {
        $languages = ['Spanish', 'French', 'German', 'Chinese', 'Japanese', 'Korean', 'Arabic'];
        return array_slice($languages, 0, rand(0, 2));
    }

    private function getCareerStage(array $roles): string
    {
        if (in_array('editor-in-chief', $roles)) {
            return 'senior';
        }

        if (in_array('associate-editor', $roles) || in_array('managing-editor', $roles)) {
            return 'mid-career';
        }

        return ['early-career', 'mid-career', 'senior'][rand(0, 2)];
    }

    private function getReviewExpertise(): array
    {
        return array_slice([
            'Methodology Review',
            'Statistical Analysis',
            'Literature Review',
            'Experimental Design',
            'Data Interpretation',
            'Technical Writing',
        ], 0, rand(2, 4));
    }

    private function getReviewPreferences(): array
    {
        return [
            'blind_review' => rand(0, 1) === 1,
            'double_blind' => rand(0, 1) === 1,
            'open_review' => rand(0, 1) === 1,
            'post_publication' => rand(0, 1) === 1,
        ];
    }

    private function hasEditorialRole(array $roles): bool
    {
        return in_array('editor-in-chief', $roles)
            || in_array('associate-editor', $roles)
            || in_array('managing-editor', $roles)
            || in_array('guest-editor', $roles);
    }

    private function getEditorialExperience(): array
    {
        return [
            [
                'journal' => 'International Journal of Science',
                'role' => 'Associate Editor',
                'from' => '2018',
                'to' => '2021',
            ],
            [
                'journal' => 'Journal of Advanced Research',
                'role' => 'Guest Editor',
                'from' => '2020',
                'to' => '2020',
            ],
        ];
    }

    private function getJournalAssociations(): array
    {
        return [
            'International Journal of Science',
            'Journal of Advanced Research',
            'Nature Communications',
        ];
    }

    private function getPublicationHistory(): array
    {
        return [
            [
                'title' => 'Novel approaches to complex problems',
                'journal' => 'Science',
                'year' => '2022',
                'citations' => rand(10, 200),
            ],
            [
                'title' => 'Advances in the field: A comprehensive review',
                'journal' => 'Nature',
                'year' => '2021',
                'citations' => rand(20, 300),
            ],
        ];
    }

    private function getPreferredManuscriptTypes(): array
    {
        return array_slice([
            'research_article',
            'review_article',
            'case_study',
            'short_communication',
            'technical_note',
        ], 0, rand(2, 3));
    }

    private function getSocialLinks(): array
    {
        return [
            'linkedin' => 'https://linkedin.com/in/researcher-' . rand(1000, 9999),
            'twitter' => 'https://twitter.com/researcher_' . rand(1000, 9999),
            'researchgate' => 'https://researchgate.net/profile/Researcher_' . rand(1000, 9999),
        ];
    }

    private function getProfessionalMemberships(): array
    {
        $memberships = [
            'American Association for the Advancement of Science',
            'IEEE',
            'ACM',
            'American Chemical Society',
            'American Physical Society',
            'American Statistical Association',
        ];

        return array_slice($memberships, 0, rand(1, 3));
    }
}
