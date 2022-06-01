<?php

namespace Test;

use Ark4ne\OpenApi\Contracts\Documentation\DescribableRequest;
use Ark4ne\OpenApi\Descriptors\Requests\DescribedRequest;
use Ark4ne\OpenApi\Descriptors\Requests\Describer;
use Ark4ne\OpenApi\Documentation\DocumentationEntry;
use Ark4ne\OpenApi\Parsers\Requests\RequestParser;
use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\TestCase;

class FormRequestParserTest extends TestCase
{
    public function testParse()
    {
        $parser = new RequestParser();

        dd($parser->parse(new MyDescribableRequest,
            (new \ReflectionCLass(DocumentationEntry::class))->newInstanceWithoutConstructor()));
    }
}

class MyDescribableRequest extends FormRequest implements DescribableRequest
{
    use DescribedRequest;

    public function describe(Describer $descriptor): void
    {
        $descriptor
            ->token()
            ->json([
                'filter' => ['array'],
                'filter.*.project' => ['required', 'in:' . implode(',', ['pinel', 'residency'])],
                'filter.*.email' => ['required', 'email'],
                'filter.*.date' => ['required', 'before_or_equal:2022-01-01'],
            ]);
    }
}
