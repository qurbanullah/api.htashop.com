<?php

namespace App\Enums;

enum TutorialTypeEnum: string
{
    case VIDEO = 'video';
    case ARTICLE = 'article';
    case COURSE = 'course';
    case WEBINAR = 'webinar';
    case GUIDE = 'guide';
    case INTERVIEW = 'interview';
    case SUCCESS_STORY = 'success-story';
    case CASE_STUDY = 'case-study';

    /**
     * Get all available types as key-value pairs
     */
    public static function options(): array
    {
        return [
            ['value' => self::VIDEO->value, 'label' => 'Video Tutorial'],
            ['value' => self::ARTICLE->value, 'label' => 'Article'],
            ['value' => self::COURSE->value, 'label' => 'Online Course'],
            ['value' => self::WEBINAR->value, 'label' => 'Webinar'],
            ['value' => self::GUIDE->value, 'label' => 'Step-by-Step Guide'],
            ['value' => self::INTERVIEW->value, 'label' => 'Interview'],
            ['value' => self::SUCCESS_STORY->value, 'label' => 'Success Story'],
            ['value' => self::CASE_STUDY->value, 'label' => 'Case Study'],
        ];
    }

    /**
     * Get label for the enum value
     */
    public function label(): string
    {
        return match ($this) {
            self::VIDEO => 'Video Tutorial',
            self::ARTICLE => 'Article',
            self::COURSE => 'Online Course',
            self::WEBINAR => 'Webinar',
            self::GUIDE => 'Step-by-Step Guide',
            self::INTERVIEW => 'Interview',
            self::SUCCESS_STORY => 'Success Story',
            self::CASE_STUDY => 'Case Study',
        };
    }
}
