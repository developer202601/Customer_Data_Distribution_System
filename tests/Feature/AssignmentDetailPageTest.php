<?php

namespace Tests\Feature;

use Tests\TestCase;

class AssignmentDetailPageTest extends TestCase
{
    public function test_call_center_assignment_detail_requires_a_session(): void
    {
        $this->get(route('cc.assignments.show', 1))
            ->assertRedirect(route('login'));
    }

    public function test_regional_billing_assignment_detail_requires_a_session(): void
    {
        $this->get(route('rb.assignments.show', 1))
            ->assertRedirect(route('login'));
    }
}
