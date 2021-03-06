<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\ServerComm\SerializationUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Elastic\Apm\TransactionDataInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class HttpServerTestEnvBase extends TestEnvBase
{
    /** @var string|null */
    protected $appCodeHostServerId = null;
    /** @var int|null */
    protected $appCodeHostServerPort = null;
    /** @var Logger */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public function isHttp(): bool
    {
        return true;
    }

    protected function sendRequestToInstrumentedApp(TestProperties $testProperties): void
    {
        $this->ensureMockApmServerRunning();
        $this->ensureAppCodeHostServerRunning($testProperties);
        TestCase::assertNotNull($this->appCodeHostServerPort);
        TestCase::assertNotNull($this->appCodeHostServerId);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Sending HTTP request `' . $testProperties->httpMethod . ' ' . $testProperties->uriPath . '\''
            . ' to ' . DbgUtil::fqToShortClassName(BuiltinHttpServerAppCodeHost::class) . '...'
        );

        $headers = [
            BuiltinHttpServerAppCodeHost::CLASS_HEADER_NAME  => $testProperties->appCodeClass,
            BuiltinHttpServerAppCodeHost::METHOD_HEADER_NAME => $testProperties->appCodeMethod,
        ];
        if (!is_null($testProperties->appCodeArgs)) {
            $headers[BuiltinHttpServerAppCodeHost::ARGUMENTS_HEADER_NAME]
                = SerializationUtil::serializeAsJson(
                    $testProperties->appCodeArgs,
                    AllComponentTestsOptionsMetadata::APP_CODE_ARGUMENTS_OPTION_NAME
                );
        }

        $response = TestHttpClientUtil::sendHttpRequest(
            $this->appCodeHostServerPort,
            $this->appCodeHostServerId,
            $testProperties->httpMethod,
            $testProperties->uriPath,
            $headers
        );
        if ($response->getStatusCode() !== $testProperties->expectedStatusCode) {
            throw new RuntimeException(
                'HTTP status code does not match the expected one.'
                . ' Expected: ' . $testProperties->expectedStatusCode
                . ', actual: ' . $response->getStatusCode()
            );
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully sent HTTP request `' . $testProperties->httpMethod . ' ' . $testProperties->uriPath . '\''
            . ' to ' . DbgUtil::fqToShortClassName(BuiltinHttpServerAppCodeHost::class) . '...'
        );
    }

    abstract protected function ensureAppCodeHostServerRunning(TestProperties $testProperties): void;

    protected function verifyRootTransactionName(
        TestProperties $testProperties,
        TransactionDataInterface $rootTransaction
    ): void {
        parent::verifyRootTransactionName($testProperties, $rootTransaction);

        if (is_null($testProperties->transactionName)) {
            TestCase::assertSame(
                $testProperties->httpMethod . ' ' . $testProperties->uriPath,
                $rootTransaction->getName()
            );
        }
    }

    protected function verifyRootTransactionType(
        TestProperties $testProperties,
        TransactionDataInterface $rootTransaction
    ): void {
        parent::verifyRootTransactionType($testProperties, $rootTransaction);

        if (is_null($testProperties->transactionType)) {
            TestCase::assertSame(Constants::TRANSACTION_TYPE_REQUEST, $rootTransaction->getType());
        }
    }
}
