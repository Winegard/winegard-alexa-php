<?php

namespace Winegard\AmazonAlexa\Validation;

use GuzzleHttp\Client;
use Winegard\AmazonAlexa\Exception\OutdatedCertExceptionException;
use Winegard\AmazonAlexa\Exception\RequestInvalidSignatureException;
use Winegard\AmazonAlexa\Exception\RequestInvalidTimestampException;
use Winegard\AmazonAlexa\Request\Request;

/**
 * This is a validator for amazon echo requests. It validates the timestamp of the request and the request signature.
 *
 * @author Maximilian Beckers <beckers.maximilian@gmail.com>
 */
class RequestValidator
{
    /**
     * Basic value for timestamp validation. 150 seconds is suggested by amazon.
     */
    const TIMESTAMP_VALID_TOLERANCE_SECONDS = 150;

    /**
     * @var Client
     */
    public $client;

    /**
     * @var int
     */
    protected $timestampTolerance;

    /**
     * @param int         $timestampTolerance
     * @param Client|null $client
     */
    public function __construct($timestampTolerance = self::TIMESTAMP_VALID_TOLERANCE_SECONDS, Client $client = null)
    {
        $this->timestampTolerance = $timestampTolerance;
        $this->client             = $client ?: new Client();
    }

    /**
     * Validate request data.
     *
     * @param Request $request
     *
     * @throws OutdatedCertExceptionException
     * @throws RequestInvalidSignatureException
     * @throws RequestInvalidTimestampException
     */
    public function validate(Request $request)
    {
        $this->validateTimestamp($request);
        try {
            $this->validateSignature($request);
        } catch (OutdatedCertExceptionException $e) {
            // load cert again and validate because temp file was outdatet.
            $this->validateSignature($request);
        }
    }

    /**
     * Validate request timestamp. Request tolerance should be 150 seconds.
     * For more details @see https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#timestamp.
     *
     * @param Request $request
     *
     * @throws RequestInvalidTimestampException
     */
    private function validateTimestamp(Request $request)
    {
        if (null === $request->request || $request->request->type === 'AlexaSkillEvent.ProactiveSubscriptionChanged' || !$request->request->validateTimestamp()) {
            return;
        }

        $differenceInSeconds = time() - $request->request->timestamp->getTimestamp();

        if ($differenceInSeconds > $this->timestampTolerance && $request->request->type !== 'AlexaSkillEvent.ProactiveSubscriptionChanged') {
            throw new RequestInvalidTimestampException('Invalid timestamp.');
        }
    }

    /**
     * Validate request signature. The steps for signature validation are described at developer page.
     *
     * @see https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#checking-the-signature-of-the-request
     *
     * @param Request $request
     *
     * @throws OutdatedCertExceptionException
     * @throws RequestInvalidSignatureException
     */
    private function validateSignature(Request $request)
    {
        if (null === $request->request || !$request->request->validateSignature()) {
            return;
        }

        // validate cert url
        $this->validateCertUrl($request);

        // generate local cert path
        $localCertPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.md5($request->signatureCertChainUrl).'.pem';

        // check if pem file is already downloaded to temp or download.
        $certData = $this->fetchCertData($request, $localCertPath);

        // openssl cert validation
        $this->verifyCert($request, $certData);

        // parse cert
        $certContent = $this->parseCertData($certData);

        // validate cert
        $this->validateCertContent($certContent, $localCertPath);
    }

    /**
     * @param Request $request
     *
     * @throws RequestInvalidSignatureException
     */
    private function validateCertUrl(Request $request)
    {
        if (false === (bool) preg_match("/https:\/\/s3.amazonaws.com(\:443)?\/echo.api\/*/i", $request->signatureCertChainUrl)) {
            throw new RequestInvalidSignatureException('Invalid cert url.');
        }
    }

    /**
     * @param Request $request
     * @param string  $localCertPath
     *
     * @throws RequestInvalidSignatureException
     *
     * @return string
     */
    private function fetchCertData(Request $request, string $localCertPath): string
    {
        if (file_exists($localCertPath)) {
            unlink($localCertPath);
        }
        
        $response = $this->client->request('GET', $request->signatureCertChainUrl);

        if ($response->getStatusCode() !== 200) {
            throw new RequestInvalidSignatureException('Can\'t fetch cert from URL.');
        }

        $certData = $response->getBody()->getContents();
        @file_put_contents($localCertPath, $certData);

        return $certData;
    }

    /**
     * @param Request $request
     * @param string  $certData
     *
     * @throws RequestInvalidSignatureException
     */
    private function verifyCert(Request $request, string $certData)
    {
        $Signature_PublicKey = @openssl_pkey_get_public($certData);
        $Signature_PublicKey_Data = @openssl_pkey_get_details($Signature_PublicKey);
        $Signature_Content_Decoded = @base64_decode($request->signature);

        if (1 !== @openssl_verify($request->amazonRequestBody, $Signature_Content_Decoded, $Signature_PublicKey_Data['key'], 'sha1')) {
            if (1 !== @openssl_verify($request->amazonRequestBody, $Signature_Content_Decoded, $certData, 'sha1')) {
                if (1 !== @openssl_verify($request->amazonRequestBody, $Signature_Content_Decoded, $Signature_PublicKey_Data['key'], 'sha256')) {
                    if (1 !== @openssl_verify($request->amazonRequestBody, $Signature_Content_Decoded, $certData, 'sha256')) {
                        throw new RequestInvalidSignatureException('Cert ssl verification failed.');
                    }
                }
            }
        }
    }

    /**
     * @param string $certData
     *
     * @throws RequestInvalidSignatureException
     *
     * @return array
     */
    private function parseCertData(string $certData): array
    {
        $certContent = @openssl_x509_parse($certData);
        if (empty($certContent)) {
            throw new RequestInvalidSignatureException('Parse cert failed.');
        }

        return $certContent;
    }

    /**
     * @param array  $cert
     * @param string $localCertPath
     *
     * @throws OutdatedCertExceptionException
     * @throws RequestInvalidSignatureException
     */
    private function validateCertContent(array $cert, string $localCertPath)
    {
        $this->validateCertSubject($cert);
        $this->validateCertValidTime($cert, $localCertPath);
    }

    /**
     * @param array $cert
     *
     * @throws RequestInvalidSignatureException
     */
    private function validateCertSubject(array $cert)
    {
        if (false === isset($cert['extensions']['subjectAltName']) ||
            false === stristr($cert['extensions']['subjectAltName'], 'echo-api.amazon.com')
        ) {
            throw new RequestInvalidSignatureException('Cert subject error.');
        }
    }

    /**
     * @param array  $cert
     * @param string $localCertPath
     *
     * @throws OutdatedCertExceptionException
     */
    private function validateCertValidTime(array $cert, string $localCertPath)
    {
        if (false === isset($cert['validTo_time_t']) || time() > $cert['validTo_time_t'] || false === isset($cert['validFrom_time_t']) || time() < $cert['validFrom_time_t']) {
            if (file_exists($localCertPath)) {
                /* @scrutinizer ignore-unhandled */ @unlink($localCertPath);
            }
            throw new OutdatedCertExceptionException('Cert is outdated.');
        }
    }
}
