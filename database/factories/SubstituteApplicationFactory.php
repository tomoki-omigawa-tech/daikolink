<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\SubstituteApplication;
use App\Models\SubstituteRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubstituteApplication>
 */
class SubstituteApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'substitute_request_id' => SubstituteRequest::factory(),
            'applicant_id' => User::factory(),
            'status' => ApplicationStatus::Pending,
        ];
    }
}
