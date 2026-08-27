<?php

namespace App\Services\Commercial;

use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialLead;

class CommercialEmailMergeService
{
    /**
     * Interpola as variáveis {{...}} do template com os dados do lead.
     *
     * @return array{subject: string, body_html: string}
     */
    public function render(
        CommercialEmailTemplate $template,
        CommercialLead $lead,
        CommercialEmailSequenceEnrollment $enrollment,
    ): array {
        $variables = $this->variablesFor($lead, $enrollment);

        return [
            'subject' => $this->interpolate($template->subject, $variables),
            'body_html' => $this->interpolate($template->body_html, $variables),
        ];
    }

    public function variablesFor(CommercialLead $lead, CommercialEmailSequenceEnrollment $enrollment): array
    {
        $firstName = trim(explode(' ', (string) $lead->contact_name)[0] ?? '');

        return [
            'contact_name' => $lead->contact_name ?? '',
            'first_name' => $firstName !== '' ? $firstName : ($lead->contact_name ?? ''),
            'company_name' => $lead->company_name ?? '',
            'email' => $lead->email ?? '',
            'phone' => $lead->phone ?? '',
            'whatsapp' => $lead->whatsapp ?? '',
            'website' => $lead->website ?? '',
            'country' => $lead->country ?? '',
            'city' => $lead->city ?? '',
            'segment' => $lead->segment ?? '',
            'sender_name' => config('app.name'),
            'unsubscribe_url' => route('public.commercial-email.unsubscribe', ['token' => $enrollment->unsubscribe_token]),
        ];
    }

    private function interpolate(string $text, array $variables): string
    {
        $text = $this->applyConditionals($text, $variables);

        $search = [];
        $replace = [];

        foreach ($variables as $key => $value) {
            $search[] = '{{'.$key.'}}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $text);
    }

    /**
     * Suporte a blocos condicionais simples no estilo {{#if variavel}}...{{/if}}:
     * mantém o conteúdo interno (com suas próprias {{variaveis}}, resolvidas no
     * passo seguinte) quando a variável existe e não está vazia, ou remove o
     * bloco inteiro quando ela é ausente/vazia. Não suporta {{else}} nem blocos
     * aninhados — só o suficiente para evitar que sintaxe tipo Handlebars vaze
     * literalmente para o e-mail quando alguém tenta usar esse padrão.
     */
    private function applyConditionals(string $text, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s',
            function (array $matches) use ($variables) {
                $value = $variables[$matches[1]] ?? '';

                return $value !== '' && $value !== null ? $matches[2] : '';
            },
            $text
        );
    }
}
