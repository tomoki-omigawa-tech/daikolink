<?php

namespace Tests\Unit;

use App\Enums\Grade;
use App\Enums\SubjectStage;
use PHPUnit\Framework\TestCase;

class GradeTest extends TestCase
{
    public function test_grade_labels_and_stages(): void
    {
        $this->assertSame('小3', Grade::E3->label());
        $this->assertSame('高3', Grade::H3->label());
        $this->assertSame(SubjectStage::Junior, Grade::J2->stage());
        $this->assertCount(4, Grade::ofStage(SubjectStage::Elementary));
    }

    public function test_subjects_available_for_each_grade(): void
    {
        $this->assertSame([SubjectStage::Elementary], Grade::E5->subjectStages());
        $this->assertSame([SubjectStage::Junior, SubjectStage::Other], Grade::J1->subjectStages());
        $this->assertSame([SubjectStage::Senior, SubjectStage::Other], Grade::H2->subjectStages());
    }
}
