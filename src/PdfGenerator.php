<?php

namespace DocBot;

use TCPDF;

class PdfGenerator
{
    private string $templatesDir;
    private string $storageDir;

    public function __construct(string $templatesDir, string $storageDir)
    {
        $this->templatesDir = rtrim($templatesDir, '/');
        $this->storageDir = rtrim($storageDir, '/');
    }

    /**
     * Генерирует PDF договора и возвращает путь к файлу.
     */
    public function generateContract(array $user, array $client, array $document): string
    {
        $html = file_get_contents($this->templatesDir . '/contract_service_template.html');
        $placeholders = $this->buildContractPlaceholders($user, $client, $document);
        $html = $this->processConditionalBlocks($html, $client['client_type']);
        $html = $this->replacePlaceholders($html, $placeholders);

        $filename = 'contract_' . $document['contract_number'] . '_' . time() . '.pdf';
        return $this->renderPdf($html, $filename, 'Договор ' . $document['contract_number']);
    }

    /**
     * Генерирует PDF акта и возвращает путь к файлу.
     */
    public function generateAct(array $user, array $client, array $contract, array $actData): string
    {
        $html = file_get_contents($this->templatesDir . '/act_template.html');
        $placeholders = $this->buildActPlaceholders($user, $client, $contract, $actData);
        $html = $this->replacePlaceholders($html, $placeholders);

        $filename = 'act_' . $actData['act_number'] . '_' . time() . '.pdf';
        return $this->renderPdf($html, $filename, 'Акт ' . $actData['act_number']);
    }

    /**
     * Генерирует PDF счёта на оплату и возвращает путь к файлу.
     */
    public function generateInvoice(array $user, array $client, array $contract, array $invoiceData): string
    {
        $html = file_get_contents($this->templatesDir . '/invoice_template.html');
        $placeholders = $this->buildInvoicePlaceholders($user, $client, $contract, $invoiceData);
        $html = $this->processConditionalBlocks($html, $client['client_type']);
        $html = $this->replacePlaceholders($html, $placeholders);

        $filename = 'invoice_' . $invoiceData['invoice_number'] . '_' . time() . '.pdf';
        return $this->renderPdf($html, $filename, 'Счёт ' . $invoiceData['invoice_number']);
    }

    private function buildInvoicePlaceholders(array $user, array $client, array $contract, array $invoiceData): array
    {
        return [
            '{{invoice_number}}'       => $invoiceData['invoice_number'],
            '{{invoice_date}}'         => $this->formatDate($invoiceData['invoice_date']),
            '{{user_full_name}}'       => $user['full_name'],
            '{{user_inn}}'             => $user['inn'],
            '{{user_bank_name}}'       => $user['bank_name'],
            '{{user_account_number}}'  => $user['account_number'],
            '{{user_bik}}'             => $user['bik'],
            '{{user_corr_account}}'    => $user['corr_account'],
            '{{client_full_name}}'     => $client['full_name'],
            '{{client_inn}}'           => $client['inn'] ?? '',
            '{{client_kpp}}'           => $client['kpp'] ?? '',
            '{{service_description}}'  => $contract['service_description'],
            '{{amount}}'               => number_format((float) $contract['amount'], 2, '.', ' '),
            '{{amount_words}}'         => $invoiceData['amount_words'],
        ];
    }

    private function buildContractPlaceholders(array $user, array $client, array $doc): array
    {
        $clientExtra = $this->buildClientExtraRequisites($client);
        $clientBankDetails = !empty($client['bank_details'])
            ? 'Банковские реквизиты:<br>' . nl2br(htmlspecialchars($client['bank_details'])) . '<br>'
            : '';
        $clientSignatory = $client['client_type'] === 'org'
            ? ($client['representative_name'] ?? $client['full_name'])
            : $client['full_name'];
        $clientDisplayName = $client['client_type'] === 'org'
            ? $client['full_name']
            : $client['full_name'];

        return [
            '{{contract_number}}'         => $doc['contract_number'],
            '{{city}}'                    => $doc['city'] ?? 'Москва',
            '{{contract_date}}'           => $this->formatDate($doc['date_start']),
            '{{executor_full_name}}'      => $user['full_name'],
            '{{executor_inn}}'            => $user['inn'],
            '{{executor_address}}'        => $user['address'],
            '{{executor_phone}}'          => $user['phone'],
            '{{executor_email}}'          => $user['email'],
            '{{executor_bank_name}}'      => $user['bank_name'],
            '{{executor_account}}'        => $user['account_number'],
            '{{executor_bik}}'            => $user['bik'],
            '{{executor_corr_account}}'   => $user['corr_account'],
            '{{client_full_name}}'        => $client['full_name'],
            '{{client_org_name}}'         => $client['full_name'],
            '{{client_inn}}'              => $client['inn'] ?? '',
            '{{client_kpp}}'              => $client['kpp'] ?? '',
            '{{client_ogrn}}'             => $client['ogrn'] ?? '',
            '{{client_ogrnip}}'           => $client['ogrnip'] ?? '',
            '{{client_representative_title}}' => $client['representative_title'] ?? '',
            '{{client_representative_name}}'  => $client['representative_name'] ?? '',
            '{{client_basis}}'            => $client['basis'] ?? 'Устава',
            '{{client_passport_series}}'  => $client['passport_series'] ?? '',
            '{{client_passport_number}}'  => $client['passport_number'] ?? '',
            '{{client_passport_issued}}'  => $client['passport_issued'] ?? '',
            '{{client_address}}'          => $client['address'] ?? '',
            '{{client_phone}}'            => $client['phone'] ?? '',
            '{{client_email}}'            => $client['email'] ?? '',
            '{{client_display_name}}'     => $clientDisplayName,
            '{{client_extra_requisites}}' => $clientExtra,
            '{{client_bank_details}}'     => $clientBankDetails,
            '{{client_signatory_name}}'   => $clientSignatory,
            '{{service_description}}'     => $doc['service_description'],
            '{{amount}}'                  => number_format((float) $doc['amount'], 2, '.', ' '),
            '{{amount_words}}'            => $doc['amount_words'],
            '{{date_start}}'              => $this->formatDate($doc['date_start']),
            '{{date_end}}'                => $this->formatDate($doc['date_end']),
            '{{payment_terms}}'           => $doc['payment_terms'] ?? 'по безналичному расчёту',
            '{{payment_days}}'            => (string) ($doc['payment_days'] ?? 5),
            '{{payment_moment}}'          => $doc['payment_moment'] ?? 'с момента подписания Акта сдачи-приёмки',
            '{{acceptance_days}}'         => (string) ($doc['acceptance_days'] ?? 5),
            '{{termination_days}}'        => (string) ($doc['termination_days'] ?? 14),
        ];
    }

    private function buildActPlaceholders(array $user, array $client, array $contract, array $actData): array
    {
        $clientInnLine = !empty($client['inn']) ? ', ИНН ' . $client['inn'] : '';
        $clientSignatory = $client['client_type'] === 'org'
            ? ($client['representative_name'] ?? $client['full_name'])
            : $client['full_name'];

        return [
            '{{act_number}}'          => $actData['act_number'],
            '{{contract_number}}'     => $contract['contract_number'],
            '{{contract_date}}'       => $this->formatDate($contract['date_start']),
            '{{city}}'                => $contract['city'] ?? 'Москва',
            '{{act_date}}'            => $this->formatDate($actData['act_date']),
            '{{executor_full_name}}'  => $user['full_name'],
            '{{executor_inn}}'        => $user['inn'],
            '{{client_display_name}}' => $client['full_name'],
            '{{client_inn_line}}'     => $clientInnLine,
            '{{client_inn}}'          => $client['inn'] ?? '',
            '{{client_signatory_name}}' => $clientSignatory,
            '{{service_description}}' => $contract['service_description'],
            '{{amount}}'              => number_format((float) $contract['amount'], 2, '.', ' '),
            '{{amount_words}}'        => $contract['amount_words'],
        ];
    }

    private function buildClientExtraRequisites(array $client): string
    {
        $lines = '';
        if ($client['client_type'] === 'ip') {
            if (!empty($client['ogrnip'])) $lines .= 'ОГРНИП: ' . $client['ogrnip'] . '<br>';
        } elseif ($client['client_type'] === 'org') {
            if (!empty($client['kpp'])) $lines .= 'КПП: ' . $client['kpp'] . '<br>';
            if (!empty($client['ogrn'])) $lines .= 'ОГРН: ' . $client['ogrn'] . '<br>';
        } elseif ($client['client_type'] === 'fiz') {
            if (!empty($client['passport_series']) && !empty($client['passport_number'])) {
                $lines .= 'Паспорт: ' . $client['passport_series'] . ' ' . $client['passport_number'] . '<br>';
            }
        }
        return $lines;
    }

    private function processConditionalBlocks(string $html, string $clientType): string
    {
        $types = ['fiz', 'ip', 'org'];
        foreach ($types as $type) {
            $pattern = '/\{\{#if client_type_' . $type . '\}\}(.*?)\{\{\/if\}\}/s';
            if ($type === $clientType) {
                $html = preg_replace($pattern, '$1', $html);
            } else {
                $html = preg_replace($pattern, '', $html);
            }
        }
        return $html;
    }

    private function replacePlaceholders(string $html, array $placeholders): string
    {
        return str_replace(array_keys($placeholders), array_values($placeholders), $html);
    }

    private function formatDate(string $date): string
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];
        $ts = strtotime($date);
        if (!$ts) return $date;
        $day = date('j', $ts);
        $month = (int) date('n', $ts);
        $year = date('Y', $ts);
        return "{$day} {$months[$month]} {$year} г.";
    }

    private function renderPdf(string $html, string $filename, string $title): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('DocBot');
        $pdf->SetAuthor('DocBot');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetFont('dejavusans', '', 11);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        $filepath = $this->storageDir . '/' . $filename;
        $pdf->Output($filepath, 'F');

        return $filepath;
    }
}
