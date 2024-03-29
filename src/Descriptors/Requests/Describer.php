<?php

namespace Ark4ne\OpenApi\Descriptors\Requests;

use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\Request\Security;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;

class Describer
{
    /** @var array<string, Security> */
    protected array $securities = [];

    /** @var array<string, Parameter> */
    protected array $headers = [];

    /** @var array<string, array{name: string, raw: mixed, description?: Parameter}> */
    protected array $body = [];

    /** @var array<string, array{name: string, raw: mixed, description?: Parameter}> */
    protected array $queries = [];

    /**
     * @return array<string, Parameter>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return array_combine(array_column($this->body, 'name'), array_column($this->body, 'rule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueries(): array
    {
        return array_combine(array_column($this->queries, 'name'), array_column($this->queries, 'rule'));
    }

    /**
     * @return array<string, Security>
     */
    public function getSecurities(): array
    {
        return $this->securities;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            $this->getQueries(),
            $this->getBody(),
        );
    }

    /**
     * Define all headers
     *
     * @param iterable<string, callable(Parameter):mixed> $headers
     *
     * @return $this
     */
    public function headers(iterable $headers): self
    {
        foreach ($headers as $name => $rule) {
            $rule($this->header($name));
        }

        return $this;
    }

    /**
     * Define a header
     *
     * @param string $name
     *
     * @return Parameter
     */
    public function header(string $name): Parameter
    {
        return $this->headers[strtolower($name)] = new Parameter($name);
    }

    /**
     * @param string $type
     *
     * @return \Ark4ne\OpenApi\Documentation\Request\Security
     */
    public function security(string $type): Security
    {
        return $this->securities[strtolower($type)] = (new Security($type))->type($type);
    }

    public function token(string $in = Security::IN_HEADER): self
    {
        $this->apiKey($in);

        return $this;
    }

    public function apiKey(string $in = Security::IN_HEADER): Security
    {
        return $this->security(Security::TYPE_API_KEY)->in($in);
    }

    public function basic(): Security
    {
        return $this->security(Security::TYPE_HTTP);
    }

    public function oauth2(): Security
    {
        return $this->security(Security::TYPE_OAUTH2);
    }

    public function openId(): Security
    {
        return $this->security(Security::TYPE_OPEN_ID_CONNECT);
    }

    /**
     * Define requirement of XSRF-Token
     *
     * @return $this
     */
    public function xsrf(): self
    {
        $this
            ->header('XSRF-Token')
            ->required()
            ->pattern("{csrf-token}");

        return $this;
    }

    /**
     * Define acceptable format
     *
     * @param array<string>|string $accept
     *
     * @return $this
     */
    public function accept(array|string $accept): self
    {
        $this
            ->header('Content-Type')
            ->required()
            ->enum((array)$accept);

        return $this;
    }

    /**
     * Define acceptable encoding
     *
     * @param array<string>|string $encoding
     *
     * @return $this
     */
    public function encoding(array|string $encoding): self
    {
        $this
            ->header('Content-Encoding')
            ->required()
            ->enum((array)$encoding);

        return $this;
    }

    /**
     * Define body rules
     *
     * @param iterable<string, Rule|string|mixed> $rules
     *
     * @return $this
     */
    public function body(iterable $rules): self
    {
        foreach ($rules as $name => $rule) {
            $this->body[strtolower($name)] = $rule instanceof Rule
                ? $rule->name($name)
                : new Rule(['name' => $name, 'rule' => $rule]);
        }

        return $this;
    }

    /**
     * Define queries rules - For GET parameters only
     *
     * @param iterable<string, Rule|string|mixed> $rules
     *
     * @return $this
     */
    public function queries(iterable $rules): self
    {
        foreach ($rules as $name => $rule) {
            $this->queries[strtolower($name)] = $rule instanceof Rule
                ? $rule->name($name)
                : new Rule(['name' => $name, 'rule' => $rule]);
        }

        return $this;
    }

    /**
     * Define rules for a json request
     *
     * @param iterable<string, mixed> $rules
     *
     * @return $this
     */
    public function json(iterable $rules): self
    {
        return $this
            ->accept(MediaType::MEDIA_TYPE_APPLICATION_JSON)
            ->body($rules);
    }

    /**
     * Define rules for a form request
     *
     * @param iterable<string, mixed> $rules
     *
     * @return $this
     */
    public function form(iterable $rules): self
    {
        return $this
            ->accept(MediaType::MEDIA_TYPE_APPLICATION_X_WWW_FORM_URLENCODED)
            ->body($rules);
    }

    /**
     * Define rules for a xml request
     *
     * @param iterable<string, mixed> $rules
     *
     * @return $this
     */
    public function xml(iterable $rules): self
    {
        return $this
            ->accept(MediaType::MEDIA_TYPE_TEXT_XML)
            ->body($rules);
    }

    /**
     * @param string|array<string|\Illuminate\Contracts\Validation\Rule>|\Illuminate\Contracts\Validation\Rule $rules
     *
     * @return \Ark4ne\OpenApi\Descriptors\Requests\Rule
     */
    public function rule(mixed $rules): Rule
    {
        return new Rule(['rule' => $rules]);
    }
}
