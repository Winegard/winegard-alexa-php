<?php

namespace Winegard\AmazonAlexa\Response\CanFulfill;

use Winegard\AmazonAlexa\Response\ResponseBodyInterface;

/**
 * @author Maximilian Beckers <beckers.maximilian@gmail.com>
 */
class CanFulfillResponseBody implements ResponseBodyInterface
{
    /**
     * @var CanFulfillIntentResponse|null
     */
    public $canFulfillIntent;

    /**
     * @param CanFulfillIntentResponse $canFulfillIntent
     *
     * @return CanFulfillResponseBody
     */
    public static function create(CanFulfillIntentResponse $canFulfillIntent): self
    {
        $canFulfillResponseBody = new self();

        $canFulfillResponseBody->canFulfillIntent = $canFulfillIntent;

        return $canFulfillResponseBody;
    }
}
