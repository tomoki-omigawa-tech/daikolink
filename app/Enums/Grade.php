<?php

namespace App\Enums;

enum Grade: string
{
    case E3 = 'e3';
    case E4 = 'e4';
    case E5 = 'e5';
    case E6 = 'e6';
    case J1 = 'j1';
    case J2 = 'j2';
    case J3 = 'j3';
    case H1 = 'h1';
    case H2 = 'h2';
    case H3 = 'h3';

    public function label(): string
    {
        return match ($this) {
            self::E3 => '小3',
            self::E4 => '小4',
            self::E5 => '小5',
            self::E6 => '小6',
            self::J1 => '中1',
            self::J2 => '中2',
            self::J3 => '中3',
            self::H1 => '高1',
            self::H2 => '高2',
            self::H3 => '高3',
        };
    }

    public function stage(): SubjectStage
    {
        return match ($this) {
            self::E3, self::E4, self::E5, self::E6 => SubjectStage::Elementary,
            self::J1, self::J2, self::J3 => SubjectStage::Junior,
            self::H1, self::H2, self::H3 => SubjectStage::Senior,
        };
    }

    public function isElementary(): bool
    {
        return $this->stage() === SubjectStage::Elementary;
    }

    /**
     * この学年で選べる科目の区分（小学生は小学、中学生は中学とその他、高校生は高校とその他）
     *
     * @return list<SubjectStage>
     */
    public function subjectStages(): array
    {
        return match ($this->stage()) {
            SubjectStage::Elementary => [SubjectStage::Elementary],
            SubjectStage::Junior => [SubjectStage::Junior, SubjectStage::Other],
            default => [SubjectStage::Senior, SubjectStage::Other],
        };
    }

    /**
     * @return list<self>
     */
    public static function ofStage(SubjectStage $stage): array
    {
        return array_values(array_filter(self::cases(), fn (self $grade) => $grade->stage() === $stage));
    }
}
