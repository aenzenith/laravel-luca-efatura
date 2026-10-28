<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

use Aenzenith\LucaEFatura\Data\Page;
use Aenzenith\LucaEFatura\Data\Record;
use Aenzenith\LucaEFatura\Data\SentDocument;
use Aenzenith\LucaEFatura\Exceptions\DocumentNotFoundException;
use Aenzenith\LucaEFatura\Support\Ettn;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * e-Arşiv fatura.
 */
class EArchive extends Module
{
    /**
     * Send a UBL-TR e-Arşiv invoice that carries its own number (cbc:ID).
     */
    public function sendXml(string $ubl, bool $sendMail = false): SentDocument
    {
        return $this->sendXmlBatch([$ubl], $sendMail)[0];
    }

    /**
     * @param  list<string>  $ubls
     * @return list<SentDocument>
     */
    public function sendXmlBatch(array $ubls, bool $sendMail = false): array
    {
        return $this->sendXmlDocuments('SendArchiveInvoiceXml', $ubls, $sendMail);
    }

    /**
     * Send a UBL-TR e-Arşiv invoice and let Luca number it.
     */
    public function sendXmlWithoutNumber(string $ubl, bool $sendMail = false): SentDocument
    {
        return $this->sendXmlDocuments('SendArchiveInvoiceXmlWithoutInvoiceNumber', [$ubl], $sendMail)[0];
    }

    /**
     * Send an invoice described with Luca's ArchiveInvoice members instead of UBL.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function send(array $invoice): SentDocument
    {
        return $this->sendBatch([$invoice])[0];
    }

    /**
     * @param  list<array<string, mixed>>  $invoices
     * @return list<SentDocument>
     */
    public function sendBatch(array $invoices): array
    {
        self::assertBatch($invoices);

        return self::sentList($this->call('SendArchiveInvoice', ['ArchiveInvoices' => $invoices])->list('ArchiveInvoices'), count($invoices));
    }

    public function find(string $ettn, bool $withPdf = false, bool $withXml = false): ?Record
    {
        $ettn = Ettn::assert($ettn);

        $result = $this->call('SearchArchiveInvoice', [
            'Ettn' => $ettn,
            'PagingRequest' => self::paging(1, 1),
            'ResultSet' => ['IsPDFIncluded' => $withPdf, 'IsXMLIncluded' => $withXml],
        ]);

        return self::matching($result->list('ArchiveInvoices'), $ettn);
    }

    public function findByExternalCode(string $code, bool $withPdf = false): ?Record
    {
        if (trim($code) === '') {
            throw new InvalidArgumentException('Luca: an external code is required.');
        }

        $result = $this->call('SearchArchiveInvoice', [
            'ExternalArchiveInvoiceCode' => $code,
            'PagingRequest' => self::paging(1, 10),
            'ResultSet' => ['IsPDFIncluded' => $withPdf],
        ]);

        foreach (Record::listFrom($result->list('ArchiveInvoices')) as $record) {
            if ($record->externalCode() === $code) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $criteria  SearchArchiveInvoiceRequest members
     * @return Page<Record>
     */
    public function search(array $criteria = [], int $page = 1, int $perPage = 50): Page
    {
        $result = $this->call('SearchArchiveInvoice', $criteria + ['PagingRequest' => self::paging($page, $perPage)]);

        return Page::from(Record::listFrom($result->list('ArchiveInvoices')), $result->get('PagingResponse'), $page, $perPage);
    }

    /**
     * PDF bytes of an e-Arşiv invoice.
     */
    public function pdf(string $ettn): string
    {
        return $this->find($ettn, withPdf: true)?->pdf() ?? throw DocumentNotFoundException::ettn($ettn);
    }

    public function cancel(string $ettn, string $reason): void
    {
        $this->cancelMany([$ettn => $reason]);
    }

    /**
     * @param  array<string, string>  $reasonsByEttn
     */
    public function cancelMany(array $reasonsByEttn): void
    {
        self::assertBatch($reasonsByEttn);

        $list = [];

        foreach ($reasonsByEttn as $ettn => $reason) {
            $list[] = ['CancellationReason' => $reason, 'ETTN' => Ettn::assert((string) $ettn)];
        }

        $this->call('CancelArchiveInvoice', ['ArchiveInvoiceList' => $list]);
    }

    /**
     * E-mail the invoice to the given address through Luca.
     */
    public function mail(string $ettn, string $email): void
    {
        $this->call('SendArchiveInvoiceMail', ['Email' => $email, 'Ettn' => Ettn::assert($ettn)]);
    }

    /**
     * Send the e-Arşiv report up to $lastDate; returns the report group number.
     */
    public function sendReport(DateTimeInterface $lastDate): ?string
    {
        $group = $this->call('SendArchiveInvoiceReport', ['LastDate' => $lastDate])->get('ArchiveInvoiceReportGroupNumber');

        return is_scalar($group) && (string) $group !== '' ? (string) $group : null;
    }

    /**
     * @return list<mixed>
     */
    public function reports(string $groupNumber, ?string $reportNumber = null): array
    {
        return $this->call('SearchArchiveInvoiceReport', [
            'ArchiveInvoiceReportGroupNumber' => $groupNumber,
            'ArchiveInvoiceReportNumber' => $reportNumber,
        ])->list('ArchiveInvoiceReportList');
    }

    /**
     * @param  list<string>  $ubls
     * @return list<SentDocument>
     */
    private function sendXmlDocuments(string $operation, array $ubls, bool $sendMail): array
    {
        self::assertBatch($ubls);
        $parsed = self::parseAll($ubls);

        $documents = array_map(
            static fn (string $ubl): array => ['ArchiveInvoiceContent' => $ubl, 'SendMailAutomatically' => $sendMail],
            $ubls,
        );

        return SentDocument::forDocuments($this->call($operation, ['ArchiveInvoices' => $documents])->list('ArchiveInvoices'), $parsed);
    }
}
