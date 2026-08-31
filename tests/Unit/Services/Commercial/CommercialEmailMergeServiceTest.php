<?php

namespace Tests\Unit\Services\Commercial;

use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialLead;
use App\Services\Commercial\CommercialEmailMergeService;
use Tests\TestCase;

class CommercialEmailMergeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://api.jornafy.test']);
    }

    private function enrollment(): CommercialEmailSequenceEnrollment
    {
        return new CommercialEmailSequenceEnrollment(['unsubscribe_token' => 'test-token-123']);
    }

    private function signatureSuffix(): string
    {
        return '<div style="margin-top:24px;">'
            .'<img src="https://api.jornafy.test/images/a1ad0979-eb80-431b-aca8-792702fdff98.png" '
            .'alt="Lorena García - Administrativo - Jornafy - +34 634 49 93 69 - administrativo@jornafy.com - www.jornafy.com - España, Valencia" '
            .'style="max-width:600px;width:100%;height:auto;display:block;"></div>';
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
        $this->assertSame('<p>Acme conta com Carlos</p>'.$this->signatureSuffix(), $result['body_html']);
    }

    public function test_conditional_block_keeps_content_when_variable_is_present(): void
    {
        $lead = new CommercialLead(['contact_name' => 'Carlos Silva']);
        $template = new CommercialEmailTemplate([
            'subject' => 'Assunto',
            'body_html' => 'Hola{{#if first_name}} {{first_name}}{{/if}},',
        ]);

        $result = (new CommercialEmailMergeService)->render($template, $lead, $this->enrollment());

        $this->assertSame('Hola Carlos,'.$this->signatureSuffix(), $result['body_html']);
    }

    public function test_conditional_block_is_removed_when_variable_is_empty(): void
    {
        $lead = new CommercialLead(['contact_name' => null]);
        $template = new CommercialEmailTemplate([
            'subject' => 'Assunto',
            'body_html' => 'Hola{{#if first_name}} {{first_name}}{{/if}},',
        ]);

        $result = (new CommercialEmailMergeService)->render($template, $lead, $this->enrollment());

        $this->assertSame('Hola,'.$this->signatureSuffix(), $result['body_html']);
    }

    public function test_conditional_referencing_unknown_variable_is_removed(): void
    {
        $lead = new CommercialLead(['contact_name' => 'Carlos Silva']);
        $template = new CommercialEmailTemplate([
            'subject' => 'Assunto',
            'body_html' => 'Hola{{#if last_name}} {{last_name}}{{/if}},',
        ]);

        $result = (new CommercialEmailMergeService)->render($template, $lead, $this->enrollment());

        $this->assertSame('Hola,'.$this->signatureSuffix(), $result['body_html']);
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

        $this->assertSame('{{#each items}}{{this}}{{/each}}'.$this->signatureSuffix(), $result['body_html']);
    }

    public function test_signature_image_is_always_appended_using_app_url(): void
    {
        $lead = new CommercialLead(['contact_name' => 'Carlos Silva']);
        $template = new CommercialEmailTemplate(['subject' => 'Assunto', 'body_html' => '<p>Corpo</p>']);

        $result = (new CommercialEmailMergeService)->render($template, $lead, $this->enrollment());

        $this->assertStringContainsString(
            'src="https://api.jornafy.test/images/a1ad0979-eb80-431b-aca8-792702fdff98.png"',
            $result['body_html']
        );
        $this->assertStringContainsString('alt="Lorena García', $result['body_html']);
    }
}
