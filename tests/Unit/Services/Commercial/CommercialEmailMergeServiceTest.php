<?php

namespace Tests\Unit\Services\Commercial;

use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialLead;
use App\Services\Commercial\CommercialEmailMergeService;
use Tests\TestCase;

class CommercialEmailMergeServiceTest extends TestCase
{
    private function enrollment(): CommercialEmailSequenceEnrollment
    {
        return new CommercialEmailSequenceEnrollment(['unsubscribe_token' => 'test-token-123']);
    }

    public function test_simple_variable_is_interpolated(): void
    {
        $lead = new CommercialLead(['contact_name' => 'Carlos Silva', 'company_name' => 'Acme']);
        $template = new CommercialEmailTemplate([
            'subject' => 'Olá {{first_name}}',
            'body_html' => '<p>{{company_name}} conta com {{first_name}}</p>',
        ]);

        $result = (new CommercialEmailMergeService)->render($template, $lead, $this->enrollment());

        $this->assertSame('Olá Carlos', $result['subject']);
        $this->assertSame('<p>Acme conta com Carlos</p>', $result['body_html']);
    }

    public function test_conditional_block_keeps_content_when_variable_is_present(): void
    {
        $lead = new CommercialLead(['contact_name' => 'Carlos Silva']);
        $template = new CommercialEmailTemplate([
            'subject' => 'Assunto',
            'body_html' => 'Hola{{#if first_name}} {{first_name}}{{/if}},',
        ]);

        $result = (new CommercialEmailMergeService)->render($template, $lead, $this->enrollment());

        $this->assertSame('Hola Carlos,', $result['body_html']);
    }

    public function test_conditional_block_is_removed_when_variable_is_empty(): void
    {
        $lead = new CommercialLead(['contact_name' => null]);
        $template = new CommercialEmailTemplate([
            'subject' => 'Assunto',
            'body_html' => 'Hola{{#if first_name}} {{first_name}}{{/if}},',
        ]);

        $result = (new CommercialEmailMergeService)->render($template, $lead, $this->enrollment());

        $this->assertSame('Hola,', $result['body_html']);
    }

    public function test_conditional_referencing_unknown_variable_is_removed(): void
    {
        $lead = new CommercialLead(['contact_name' => 'Carlos Silva']);
        $template = new CommercialEmailTemplate([
            'subject' => 'Assunto',
            'body_html' => 'Hola{{#if last_name}} {{last_name}}{{/if}},',
        ]);

        $result = (new CommercialEmailMergeService)->render($template, $lead, $this->enrollment());

        $this->assertSame('Hola,', $result['body_html']);
    }

    public function test_unsupported_handlebars_syntax_without_hash_is_not_touched(): void
    {
        $lead = new CommercialLead(['contact_name' => 'Carlos Silva']);
        $template = new CommercialEmailTemplate([
            'subject' => 'Assunto',
            // Sintaxe Handlebars de loop/helper não suportada — deve permanecer literal.
            'body_html' => '{{#each items}}{{this}}{{/each}}',
        ]);

        $result = (new CommercialEmailMergeService)->render($template, $lead, $this->enrollment());

        $this->assertSame('{{#each items}}{{this}}{{/each}}', $result['body_html']);
    }
}
