<?php

namespace Winegard\AmazonAlexa\Request\Request\Standard;

use Winegard\AmazonAlexa\Request\Request\AbstractRequest;

/**
 * @author Maximilian Beckers <beckers.maximilian@gmail.com>
 */
class LaunchRequest extends StandardRequest
{
    const TYPE = 'LaunchRequest';

    /**
     * @inheritdoc
     */
    public static function fromAmazonRequest(array $amazonRequest): AbstractRequest
    {
        $request = new self();

        $request->type = self::TYPE;
        $request->setRequestData($amazonRequest);

        return $request;
    }
}
