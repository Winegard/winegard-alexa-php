<?php

namespace Winegard\AmazonAlexa\Request\Request\PlaybackController;

use Winegard\AmazonAlexa\Request\Request\AbstractRequest;

/**
 * @author Maximilian Beckers <beckers.maximilian@gmail.com>
 */
class PreviousCommandIssued extends AbstractPlaybackController
{
    const TYPE = 'PlaybackController.PreviousCommandIssued';

    /**
     * @inheritdoc
     */
    public static function fromAmazonRequest(array $amazonRequest): AbstractRequest
    {
        $nextCommandIssued = new self();

        $nextCommandIssued->type = self::TYPE;

        $nextCommandIssued->setRequestData($amazonRequest);

        return $nextCommandIssued;
    }
}
