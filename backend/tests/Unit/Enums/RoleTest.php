<?php

namespace Tests\Unit\Enums;

use App\Enums\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleTest extends TestCase
{
    #[Test]
    public function it_has_the_expected_cases_and_values(): void
    {
        $this->assertSame('customer', Role::Customer->value);
        $this->assertSame('administrator', Role::Administrator->value);
    }

    #[Test]
    public function it_exposes_a_human_readable_label(): void
    {
        $this->assertSame('Customer', Role::Customer->label());
        $this->assertSame('Administrator', Role::Administrator->label());
    }
}
