<?php

namespace Ark4ne\OpenApi\Descriptors\Requests;

trait DescribedRequest
{
    private Describer $descriptor;

    public function rules()
    {
        return $this->describer()->rules();
    }

    public function describer(): Describer
    {
        if (!isset($this->descriptor)) {
            $this->describe($this->descriptor = new Describer);
        }

        return $this->descriptor;
    }
}
