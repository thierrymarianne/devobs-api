<?php

namespace WeavingTheWeb\Bundle\ApiBundle\Entity;

use Symfony\Component\HttpFoundation\Request;

final class AggregateIdentity
{
    private $id;

    /**
     * @param int $id
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function __toString()
    {
        return (string) $this->id;
    }

    /**
     * @param Request $request
     * @return AggregateIdentity|null
     */
    public function fromRequest(Request $request): ?AggregateIdentity
    {
        $aggregateIdentity = null;
        if ($request->get('aggregateId')) {
            $aggregateIdentity = new AggregateIdentity(intval($request->get('aggregateId')));
        }

        return $aggregateIdentity;
    }
}
