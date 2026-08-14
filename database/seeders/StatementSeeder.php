<?php

namespace Database\Seeders;

use App\Models\Statement;
use Illuminate\Database\Seeder;

class StatementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statements = [
            [
                'key' => 'conflict_of_interest',
                'label' => 'Potential Conflict of Interest',
                'description' => 'All authors must disclose any financial or personal relationships that could potentially bias or be perceived to bias their work. This includes employment, consultancies, stock ownership, honoraria, paid expert testimony, patent applications, and grants or other funding. If you have no conflicts to declare, please state "The authors declare no conflict of interest."',
                'type' => 'boolean',
                'is_required' => true,
                'is_active' => true,
                'order' => 1,
                'help_text' => 'Select "Yes" to declare conflicts or "No" if no conflicts exist.',
                'metadata' => [
                    'detail_required_if' => null,
                    'detail_label' => 'Declaration of Conflict of Interest',
                    'detail_placeholder' => 'Please declare your conflict of interest status. If no conflicts exist, state: "The authors declare no conflict of interest." If conflicts exist, describe them in detail...',
                    'always_show_details' => true
                ]
            ],
            [
                'key' => 'genai_usage',
                'label' => 'Use of Generative AI in Writing',
                'description' => 'Authors must disclose if any generative AI tools (such as ChatGPT, Claude, GPT-4, or similar language models) were used in the preparation of this manuscript. This includes assistance with writing, editing, data analysis, or figure generation. If used, please specify which tools were used and for what purposes. Note: AI cannot be listed as an author, and authors remain fully responsible for the content.',
                'type' => 'boolean',
                'is_required' => true,
                'is_active' => true,
                'order' => 2,
                'help_text' => 'Select "Yes" if any AI tools were used, or "No" if no AI was used in preparing this manuscript.',
                'metadata' => [
                    'detail_required_if' => null,
                    'detail_label' => 'Generative AI Usage Details',
                    'detail_placeholder' => 'If AI was not used, state: "No generative AI tools were used in the preparation of this manuscript." If AI was used, please specify which tools and describe their role in manuscript preparation...',
                    'always_show_details' => true
                ]
            ],
            [
                'key' => 'prior_publication',
                'label' => 'Published Materials Elsewhere',
                'description' => 'Has any part of this manuscript been previously published or is currently under consideration for publication elsewhere? This includes conference proceedings, preprints, institutional repositories, or other journals. Duplicate publication is a serious breach of publication ethics. If this is a substantially expanded or revised version of previously published work, please provide full details and ensure proper attribution.',
                'type' => 'boolean',
                'is_required' => true,
                'is_active' => true,
                'order' => 3,
                'help_text' => 'Select "Yes" if any content has been published elsewhere or "No" if this is original.',
                'metadata' => [
                    'detail_required_if' => null,
                    'detail_label' => 'Prior Publication Details',
                    'detail_placeholder' => 'If not previously published, state: "This manuscript is original and has not been published or submitted elsewhere." If previously published or under consideration, provide complete details including citations, DOIs, and URLs...',
                    'always_show_details' => true
                ]
            ],
            [
                'key' => 'funding_information',
                'label' => 'Funding Information',
                'description' => 'Please disclose all sources of financial support for this research, including grants, fellowships, contracts, or institutional funding. Include grant numbers when available. This information helps ensure transparency and compliance with funder requirements. If the research received no specific funding, please state "This research received no external funding."',
                'type' => 'boolean',
                'is_required' => true,
                'is_active' => true,
                'order' => 4,
                'help_text' => 'Provide details about all funding sources for this research.',
                'metadata' => [
                    'detail_required_if' => null,
                    'detail_label' => 'Funding Sources and Details',
                    'detail_placeholder' => 'List all funding sources, funding agencies, grant numbers, and any acknowledgments of financial support. If no external funding was received, state: "This research received no external funding."...',
                    'always_show_details' => true
                ]
            ],
            [
                'key' => 'alternative_journals',
                'label' => 'Alternative Journal Suggestions',
                'description' => 'If your manuscript is not suitable for this journal or requires revisions beyond the scope of acceptance, would you be interested in recommendations for alternative journals within our publishing group or partner organizations? This helps expedite the publication process through journal transfer programs where appropriate.',
                'type' => 'boolean',
                'is_required' => false,
                'is_active' => true,
                'order' => 5,
                'help_text' => 'Select "Yes" if you would like to receive alternative journal suggestions if your manuscript is not accepted.',
                'metadata' => [
                    'requires_details_if_yes' => false
                ]
            ],
            [
                'key' => 'figure_permissions',
                'label' => 'Figures Permission',
                'description' => 'All figures, tables, and supplementary materials included in this manuscript must be original, properly attributed, or used with explicit permission from the copyright holder. If figures or tables are reproduced from previously published work or other sources, you must have obtained written permission from the original author/publisher and provide a proper attribution or copyright notice. Please confirm that you have obtained all necessary permissions for any figures not original to this work.',
                'type' => 'boolean',
                'is_required' => true,
                'is_active' => true,
                'order' => 6,
                'help_text' => 'Confirm that you have obtained permission from the author/publisher for all figures and tables used in this manuscript.',
                'metadata' => [
                    'requires_details_if_yes' => false
                ]
            ],
            [
                'key' => 'data_availability',
                'label' => 'Data Availability Statement',
                'description' => 'Please indicate how the data supporting your findings can be accessed. Data transparency is essential for research reproducibility. Select the appropriate data availability statement or provide a custom statement. If data cannot be shared due to privacy, ethical, or commercial restrictions, please explain why.',
                'type' => 'select',
                'is_required' => true,
                'is_active' => true,
                'order' => 7,
                'help_text' => 'Select the statement that best describes the availability of your research data.',
                'options' => [
                    'publicly_available' => 'All data are publicly available in online repositories (please provide DOI or URL)',
                    'supplementary' => 'All data are included in the manuscript and/or supplementary files',
                    'upon_request' => 'Data are available from the authors upon reasonable request',
                    'third_party' => 'Data are available from third-party sources (please provide details)',
                    'restricted' => 'Data cannot be shared due to privacy/ethical/commercial restrictions (please explain)',
                    'not_applicable' => 'No new data were created or analyzed in this study',
                    'custom' => 'Custom statement (please provide details)'
                ],
                'metadata' => [
                    'detail_required_if' => null,
                    'detail_label' => 'Data Availability Details',
                    'detail_placeholder' => 'Provide repository links, accession numbers, access restrictions, or explanations as needed for your selected statement...',
                    'always_show_details' => true
                ]
            ]
        ];

        foreach ($statements as $statement) {
            Statement::updateOrCreate(
                ['key' => $statement['key']],
                $statement
            );
        }

        $this->command->info('Statement seeder completed successfully. Created/Updated ' . count($statements) . ' statements.');
    }
}
