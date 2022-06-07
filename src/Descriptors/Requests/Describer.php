<?php

namespace Ark4ne\OpenApi\Descriptors\Requests;

use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\Request\Security;

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
        return array_combine(array_column($this->body, 'name'), array_column($this->body, 'raw'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueries(): array
    {
        return array_combine(array_column($this->body, 'name'), array_column($this->body, 'raw'));
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
            ->header('Accept')
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
            ->header('Accept-Encoding')
            ->required()
            ->enum((array)$encoding);

        return $this;
    }

    /**
     * Define body rules
     *
     * @param iterable<string, mixed> $rules
     *
     * @return $this
     */
    public function body(iterable $rules): self
    {
        foreach ($rules as $name => $rule) {
            $this->body[strtolower($name)] = [
                'name' => $name,
                'raw' => $rule,
            ];
        }

        return $this;
    }

    /**
     * Define queries rules - For get parameters only
     *
     * @param iterable<string, mixed> $rules
     *
     * @return $this
     */
    public function queries(iterable $rules): self
    {
        foreach ($rules as $name => $rule) {
            $this->queries[strtolower($name)] = [
                'name' => $name,
                'rule' => $rule
            ];
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
            ->accept('application/json')
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
            ->accept(['application/x-www-form-urlencoded', 'multipart/form-data'])
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
            ->accept('application/xml')
            ->body($rules);
    }
}
