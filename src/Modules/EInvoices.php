<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

use Aenzenith\LucaEFatura\Data\Page;
use Aenzenith\LucaEFatura\Data\Record;
use Aenzenith\LucaEFatura\Data\SentDocument;
use Aenzenith\LucaEFatura\Enums\InvoiceDirection;
use Aenzenith\LucaEFatura\Enums\InvoiceResponse;
use Aenzenith\LucaEFatura\Exceptions\DocumentNotFoundException;
use Aenzenith\LucaEFatura\Support\Ettn;

/**
 * e-Fatura: sending, searching, and answering incoming commercial invoices.
 */
class EInvoices extends Module
{
    /**
     * Send a UBL-TR e-Fatura that carries its own number (cbc:ID) to the
     * receiver's alias ("urn:mail:defaultpk@...", see TaxPayers::aliases()).
     */
    public function sendXml(string $ubl, string $receiverTag): SentDocument
    {
        return $this->sendXmlBatch([['ubl' => $ubl, 'receiverTag' => $receiverTag]])[0];
    }

    /**
     * @param  list<array{ubl: string, receiverTag: string}>  $documents
     * @return list<SentDocument>
     */
    public function sendXmlBatch(array $documents): array
    {
        return $this->sendXmlDocuments('SendInvoiceXml', $documents);
    }

    /**
     * Send a UBL-TR e-Fatura and let Luca number it.
     */
    public function sendXmlWithoutNumber(string $ubl, string $receiverTag): SentDocument
    {
        return $this->sendXmlDocuments('SendInvoiceXmlWithoutInvoiceNumber', [['ubl' => $ubl, 'receiverTag' => $receiverTag]])[0];
    }

    /**
     * Send an invoice described with Luca's Invoice members instead of UBL.
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

        return self::sentList($this->call('SendInvoice', ['Invoices' => $invoices])->list('Invoices'), count($invoices));
    }

    public function find(
        string $ettn,
        InvoiceDirection $direction = InvoiceDirection::Outgoing,
        bool $withPdf = false,
        bool $withXml = false,
    ): ?Record {
        $ettn = Ettn::assert($ettn);

        $result = $this->call('SearchInvoice', [
            'Ettn' => $ettn,
            'InvoiceDirection' => $direction,
            'PagingRequest' => self::paging(1, 1),
            'ResultSet' => ['IsPDFIncluded' => $withPdf, 'IsXMLIncluded' => $withXml],
        ]);

        return self::matching($result->list('Invoices'), $ettn);
    }

    /**
     * @param  array<string, mixed>  $criteria  SearchInvoiceRequest members
     * @return Page<Record>
     */
    public function search(array $criteria = [], int $page = 1, int $perPage = 50, InvoiceDirection $direction = InvoiceDirection::Outgoing): Page
    {
        $result = $this->call('SearchInvoice', $criteria + [
            'InvoiceDirection' => $direction,
            'PagingRequest' => self::paging($page, $perPage),
        ]);

        return Page::from(Record::listFrom($result->list('Invoices')), $result->get('PagingResponse'), $page, $perPage);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return Page<Record>
     */
    public function incoming(array $criteria = [], int $page = 1, int $perPage = 50): Page
    {
        return $this->search($criteria, $page, $perPage, InvoiceDirection::Incoming);
    }

    public function pdf(string $ettn, InvoiceDirection $direction = InvoiceDirection::Outgoing): string
    {
        return $this->find($ettn, $direction, withPdf: true)?->pdf() ?? throw DocumentNotFoundException::ettn($ettn);
    }

    /**
     * Accept an incoming commercial invoice (KABUL).
     */
    public function accept(string $ettn, ?string $description = null): void
    {
        $this->reply($ettn, InvoiceResponse::Accept, $description);
    }

    /**
     * Reject an incoming commercial invoice (RED).
     */
    public function reject(string $ettn, string $description): void
    {
        $this->reply($ettn, InvoiceResponse::Reject, $description);
    }

    public function reply(string $ettn, InvoiceResponse $response, ?string $description = null): void
    {
        $this->call('SendInvoiceReply', [
            'InvoiceReplies' => [[
                'InvoiceETTN' => Ettn::assert($ettn),
                'InvoiceResponse' => $response,
                'InvoiceResponseDescription' => $description,
            ]],
        ]);
    }

    /**
     * ETTNs matching the criteria (GetEttnList).
     *
     * @param  array<string, mixed>  $criteria  SearchAllInvoiceRequest members
     * @return list<string>
     */
    public function ettns(array $criteria = [], InvoiceDirection $direction = InvoiceDirection::Outgoing): array
    {
        $list = $this->call('GetEttnList', $criteria + ['InvoiceDirection' => $direction])->list('EttnList');

        return array_values(array_filter(array_map(
            static fn (mixed $ettn): string => is_scalar($ettn) ? (string) $ettn : '',
            $list,
        )));
    }

    /**
     * @param  list<string>  $ettns
     */
    public function archive(array $ettns, InvoiceDirection $direction = InvoiceDirection::Outgoing): void
    {
        $this->call('ArchiveInvoice', ['InvoiceDirection' => $direction, 'InvoiceETTNList' => array_map(Ettn::assert(...), $ettns)]);
    }

    /**
     * @param  list<string>  $ettns
     */
    public function unarchive(array $ettns, InvoiceDirection $direction = InvoiceDirection::Outgoing): void
    {
        $this->call('DeArchiveInvoice', ['InvoiceDirection' => $direction, 'InvoiceETTNList' => array_map(Ettn::assert(...), $ettns)]);
    }

    /**
     * @param  list<array{ubl: string, receiverTag: string}>  $documents
     * @return list<SentDocument>
     */
    private function sendXmlDocuments(string $operation, array $documents): array
    {
        self::assertBatch($documents);
        $parsed = self::parseAll(array_column($documents, 'ubl'));

        $invoices = array_map(
            static fn (array $document): array => [
                'InvoiceContent' => $document['ubl'],
                'ReceiverTag' => self::requireTag($document['receiverTag']),
            ],
            $documents,
        );

        return SentDocument::forDocuments($this->call($operation, ['Invoices' => $invoices])->list('Invoices'), $parsed);
    }
}
