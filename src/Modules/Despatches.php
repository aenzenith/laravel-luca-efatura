<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

use Aenzenith\LucaEFatura\Data\Page;
use Aenzenith\LucaEFatura\Data\Record;
use Aenzenith\LucaEFatura\Data\SentDocument;
use Aenzenith\LucaEFatura\Enums\InvoiceDirection;
use Aenzenith\LucaEFatura\Exceptions\DocumentNotFoundException;
use Aenzenith\LucaEFatura\Support\Ettn;

/**
 * e-İrsaliye and irsaliye yanıtı (receipt advice).
 */
class Despatches extends Module
{
    public function sendXml(string $ubl, string $receiverTag): SentDocument
    {
        return $this->sendXmlDocuments('SendDespatchAdviceXml', 'DespatchAdvices', 'DespatchAdviceContent', [['ubl' => $ubl, 'receiverTag' => $receiverTag]])[0];
    }

    /**
     * @param  list<array{ubl: string, receiverTag: string}>  $documents
     * @return list<SentDocument>
     */
    public function sendXmlBatch(array $documents): array
    {
        return $this->sendXmlDocuments('SendDespatchAdviceXml', 'DespatchAdvices', 'DespatchAdviceContent', $documents);
    }

    public function sendXmlWithoutNumber(string $ubl, string $receiverTag): SentDocument
    {
        return $this->sendXmlDocuments('SendDespatchAdviceXmlWithoutDespatchAdviceNumber', 'DespatchAdvices', 'DespatchAdviceContent', [['ubl' => $ubl, 'receiverTag' => $receiverTag]])[0];
    }

    /**
     * @param  array<string, mixed>  $despatch  DespatchAdvice members
     */
    public function send(array $despatch): SentDocument
    {
        return self::sentList($this->call('SendDespatchAdvice', ['DespatchAdvices' => [$despatch]])->list('DespatchAdvices'), 1)[0];
    }

    public function find(string $ettn, InvoiceDirection $direction = InvoiceDirection::Outgoing, bool $withPdf = false, bool $withXml = false): ?Record
    {
        $ettn = Ettn::assert($ettn);

        $result = $this->call('SearchDespatchAdvice', [
            'DespatchAdviceDirection' => $direction,
            'Ettn' => $ettn,
            'PagingRequest' => self::paging(1, 1),
            'ResultSet' => ['IsPDFIncluded' => $withPdf, 'IsXMLIncluded' => $withXml],
        ]);

        return self::matching($result->list('DespatchAdvices'), $ettn);
    }

    /**
     * @param  array<string, mixed>  $criteria  SearchDespatchAdviceRequest members
     * @return Page<Record>
     */
    public function search(array $criteria = [], int $page = 1, int $perPage = 50, InvoiceDirection $direction = InvoiceDirection::Outgoing): Page
    {
        $result = $this->call('SearchDespatchAdvice', $criteria + [
            'DespatchAdviceDirection' => $direction,
            'PagingRequest' => self::paging($page, $perPage),
        ]);

        return Page::from(Record::listFrom($result->list('DespatchAdvices')), $result->get('PagingResponse'), $page, $perPage);
    }

    public function pdf(string $ettn, InvoiceDirection $direction = InvoiceDirection::Outgoing): string
    {
        return $this->find($ettn, $direction, withPdf: true)?->pdf() ?? throw DocumentNotFoundException::ettn($ettn);
    }

    /**
     * Send an irsaliye yanıtı (UBL-TR ReceiptAdvice) for a received despatch.
     */
    public function sendReceiptXml(string $ubl, string $receiverTag): SentDocument
    {
        return $this->sendXmlDocuments('SendReceiptAdviceXml', 'ReceiptAdvices', 'ReceiptAdviceContent', [['ubl' => $ubl, 'receiverTag' => $receiverTag]])[0];
    }

    /**
     * @param  array<string, mixed>  $criteria  SearchReceiptAdviceRequest members
     * @return Page<Record>
     */
    public function searchReceipts(array $criteria = [], int $page = 1, int $perPage = 50, InvoiceDirection $direction = InvoiceDirection::Outgoing): Page
    {
        $result = $this->call('SearchReceiptAdvice', $criteria + [
            'PagingRequest' => self::paging($page, $perPage),
            'ReceiptAdviceDirection' => $direction,
        ]);

        return Page::from(Record::listFrom($result->list('ReceiptAdvices')), $result->get('PagingResponse'), $page, $perPage);
    }

    /**
     * @param  list<array{ubl: string, receiverTag: string}>  $documents
     * @return list<SentDocument>
     */
    private function sendXmlDocuments(string $operation, string $listField, string $contentField, array $documents): array
    {
        self::assertBatch($documents);
        $parsed = self::parseAll(array_column($documents, 'ubl'));

        $items = array_map(
            static fn (array $document): array => [
                $contentField => $document['ubl'],
                'ReceiverTag' => self::requireTag($document['receiverTag']),
            ],
            $documents,
        );

        return SentDocument::forDocuments($this->call($operation, [$listField => $items])->list($listField), $parsed);
    }
}
