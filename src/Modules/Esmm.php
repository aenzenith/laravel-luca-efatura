<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

use Aenzenith\LucaEFatura\Data\Page;
use Aenzenith\LucaEFatura\Data\Record;
use Aenzenith\LucaEFatura\Data\SentDocument;
use Aenzenith\LucaEFatura\Exceptions\DocumentNotFoundException;
use Aenzenith\LucaEFatura\Support\Ettn;

/**
 * e-SMM (serbest meslek makbuzu).
 */
class Esmm extends Module
{
    /**
     * Send a UBL-TR e-SMM (ProfileID EARSIVBELGE).
     */
    public function sendXml(string $ubl): SentDocument
    {
        $parsed = self::parseAll([$ubl]);

        return SentDocument::forDocuments([$this->call('SendESMMXml', ['ESMM' => $ubl])->data()], $parsed)[0];
    }

    /**
     * Send an e-SMM described with Luca's ESMM members instead of UBL.
     *
     * @param  array<string, mixed>  $esmm
     */
    public function send(array $esmm): SentDocument
    {
        return SentDocument::fromArray($this->call('SendESMM', ['ESMM' => $esmm])->data());
    }

    public function find(string $ettn, bool $withPdf = false, bool $withXml = false): ?Record
    {
        $ettn = Ettn::assert($ettn);

        $result = $this->call('SearchESMM', [
            'Ettn' => $ettn,
            'PagingRequest' => self::paging(1, 1),
            'ResultSet' => ['IsPDFIncluded' => $withPdf, 'IsXMLIncluded' => $withXml],
        ]);

        return self::matching($result->list('ESMM'), $ettn);
    }

    /**
     * @param  array<string, mixed>  $criteria  SearchESMMRequest members
     * @return Page<Record>
     */
    public function search(array $criteria = [], int $page = 1, int $perPage = 50): Page
    {
        $result = $this->call('SearchESMM', $criteria + ['PagingRequest' => self::paging($page, $perPage)]);

        return Page::from(Record::listFrom($result->list('ESMM')), $result->get('PagingResponse'), $page, $perPage);
    }

    public function pdf(string $ettn): string
    {
        return $this->find($ettn, withPdf: true)?->pdf() ?? throw DocumentNotFoundException::ettn($ettn);
    }

    public function cancel(string $ettn, string $reason): void
    {
        $this->call('CancelESMM', ['ESMM' => ['CancellationReason' => $reason, 'ETTN' => Ettn::assert($ettn)]]);
    }
}
