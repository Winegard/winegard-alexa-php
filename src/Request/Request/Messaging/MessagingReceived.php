<?php

namespace Winegard\AmazonAlexa\Request\Request\Messaging;

use Winegard\AmazonAlexa\Request\Request\AbstractRequest;

/**
 * @author Nicholas Bekeris <nick.bekeris@winegard.com>
 */
class MessagingReceived extends AbstractRequest
{
    /**
     * @var string
     */
    public $requestId;

    /**
     * @var string
     */
    public $message;

    const TYPE = 'Messaging.MessageReceived';

    /**
     * @param array $amazonRequest
     */
    protected function setRequestData(array $amazonRequest)
    {
        $this->requestId = $amazonRequest['requestId'];
        $this->message = $amazonRequest['message']['verbiage'];

        $this->setTime('timestamp', $amazonRequest['timestamp']);
    }

    private function setTime($attribute, $value)
    {
        //Workaround for amazon developer console sending unix timestamp
        try {
            $this->{$attribute} = new \DateTime($value);
        } catch (\Exception $e) {
            $this->{$attribute} = (new \DateTime())->setTimestamp(intval($value / 1000));
        }
    }

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
