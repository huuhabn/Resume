<?php declare(strict_types=1);

namespace DaveRandom\Resume;

final class ResourceServlet
{
    /**
     * @var \DaveRandom\Resume\Resource
     */
    private $resource;

    /**
     * Generate the default response headers for this resource
     *
     * @return HeaderSet
     */
    private function generateDefaultHeaders(): HeaderSet
    {
        $ranges = $this->resource instanceof RangeUnitProvider
            ? \implode(',', $this->resource->getRangeUnits())
            : 'bytes';

        if ($ranges === '') {
            $ranges = 'none';
        }

        return new HeaderSet([
            'content-type' => $this->resource->getMimeType(),
            'content-length' => (string)$this->resource->getLength(),
            'accept-ranges' => $ranges,
        ]);
    }

    /**
     * Send the headers that are included regardless of whether a range was requested
     *
     * @param OutputWriter $outputWriter
     * @param HeaderSet $headers
     */
    private function sendHeaders(OutputWriter $outputWriter, HeaderSet $headers)
    {
        foreach ($this->resource->getAdditionalHeaders() as $name => $value) {
            $headers->setHeader($name, $value);
        }

        foreach ($headers as $name => $value) {
            $outputWriter->sendHeader(\trim($name), \trim($value));
        }
    }

    /**
     * Create a Content-Range header corresponding to the specified unit and ranges
     *
     * @param string $unit
     * @param Range[] $ranges
     * @param int $size
     * @return string
     */
    private function getContentRangeHeader(string $unit, array $ranges, int $size): string
    {
        return $unit . ' ' . \implode(',', $ranges) . '/' . $size;
    }

    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Send data from a file based on the current Range header
     *
     * @param RangeSet|null $rangeSet Range header on which the transmission will be based
     * @param OutputWriter|null $outputWriter Output writer via which resource will be sent
     */
    public function sendResource(RangeSet $rangeSet = null, OutputWriter $outputWriter = null)
    {
        $outputWriter = $outputWriter ?? new DefaultOutputWriter();
        $headers = $this->generateDefaultHeaders();

        if ($rangeSet === null) {
            // No ranges requested, just send the whole file
            $outputWriter->setResponseCode(200);
            $this->sendHeaders($outputWriter, $headers);
            $this->resource->sendData($outputWriter);

            return;
        }

        // Send the requested ranges
        $size = $this->resource->getLength();
        $ranges = $rangeSet->getRangesForSize($size);

        $outputWriter->setResponseCode(206);
        $this->sendHeaders($outputWriter, $headers);
        $outputWriter->sendHeader('Content-Range', $this->getContentRangeHeader($rangeSet->getUnit(), $ranges, $size));

        foreach ($ranges as $range) {
            $this->resource->sendData($outputWriter, $range);
        }
    }
}
