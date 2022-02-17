<?php

namespace eLife\Journal\Guzzle;

use Google\Service\Sheets;

final class UnsubscribeOptOutReason implements UnsubscribeOptOutReasonInterface
{
    private $sheets;
    private $sheetId;

    public function __construct(Sheets $sheets, $sheetId)
    {
        $this->sheets = $sheets;
        $this->sheetId = $sheetId;
    }

    public function record($reasons = [], string $other = null, string $identifier = null)
    {
        $values = new Sheets\ValueRange([
            'values' => [],
        ]);

        $response = $this->sheets->spreadsheets_values->append($this->sheetId, 'A1:G1', $values);
    }
}
