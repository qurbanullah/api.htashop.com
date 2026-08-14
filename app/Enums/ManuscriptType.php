<?php

namespace App\Enums;

enum ManuscriptType: string
{
    case RESEARCH_ARTICLE = 'research_article';
    case REVIEW_ARTICLE = 'review_article';
    case SHORT_COMMUNICATION = 'short_communication';
    case CASE_STUDY = 'case_study';
    case TECHNICAL_NOTE = 'technical_note';
    case EDITORIAL = 'editorial';
    case LETTER_TO_EDITOR = 'letter_to_editor';
    case BOOK_REVIEW = 'book_review';
    case CONFERENCE_PAPER = 'conference_paper';
    case THESIS = 'thesis';
    case DISSERTATION = 'dissertation';

    public function label(): string
    {
        return match($this) {
            self::RESEARCH_ARTICLE => 'Research Article',
            self::REVIEW_ARTICLE => 'Review Article',
            self::SHORT_COMMUNICATION => 'Short Communication',
            self::CASE_STUDY => 'Case Study',
            self::TECHNICAL_NOTE => 'Technical Note',
            self::EDITORIAL => 'Editorial',
            self::LETTER_TO_EDITOR => 'Letter to Editor',
            self::BOOK_REVIEW => 'Book Review',
            self::CONFERENCE_PAPER => 'Conference Paper',
            self::THESIS => 'Thesis',
            self::DISSERTATION => 'Dissertation',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::RESEARCH_ARTICLE => 'Original research with novel findings and comprehensive methodology',
            self::REVIEW_ARTICLE => 'Comprehensive review of existing literature on a specific topic',
            self::SHORT_COMMUNICATION => 'Brief report of preliminary or significant findings',
            self::CASE_STUDY => 'Detailed analysis of a particular case or instance',
            self::TECHNICAL_NOTE => 'Technical methodology or tool description',
            self::EDITORIAL => 'Editorial commentary or opinion piece',
            self::LETTER_TO_EDITOR => 'Brief communication to the journal editor',
            self::BOOK_REVIEW => 'Critical review of published books',
            self::CONFERENCE_PAPER => 'Paper presented at academic conferences',
            self::THESIS => 'Master\'s thesis submission',
            self::DISSERTATION => 'Doctoral dissertation submission',
        };
    }
}
