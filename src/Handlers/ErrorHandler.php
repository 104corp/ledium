<?php


namespace Sledium\Handlers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sledium\Exceptions\HttpException;
use Sledium\Interfaces\ErrorHandlerInterface;
use Sledium\Interfaces\ErrorRendererInterface;
use Sledium\Interfaces\ErrorReporterInterface;
use Slim\Http\Body;

class ErrorHandler implements ErrorHandlerInterface
{
    use DetermineContentTypeAbleTrait;

    /** @var  ErrorReporterInterface */
    private $reporter;

    /** @var  ErrorRendererInterface */
    private $renderer;

    /** @var  bool */
    private $displayErrorDetails = false;

    /**
     * HttpErrorHandler constructor.
     * @param ErrorRendererInterface $renderer
     * @param ErrorReporterInterface $reporter
     * @param bool $displayErrorDetails
     */
    public function __construct(
        ErrorRendererInterface $renderer,
        ErrorReporterInterface $reporter,
        bool $displayErrorDetails
    ) {
        $this->renderer = $renderer;
        $this->reporter = $reporter;
        $this->displayErrorDetails = $displayErrorDetails;
    }

    /**
     * @return ErrorReporterInterface
     */
    public function getReporter(): ErrorReporterInterface
    {
        return $this->reporter;
    }

    /**
     * @param ErrorReporterInterface $reporter
     */
    public function setReporter(ErrorReporterInterface $reporter)
    {
        $this->reporter = $reporter;
    }

    /**
     * @return ErrorRendererInterface
     */
    public function getRenderer(): ErrorRendererInterface
    {
        return $this->renderer;
    }

    /**
     * @param ErrorRendererInterface $renderer
     */
    public function setRenderer(ErrorRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * @return bool
     */
    public function isDisplayErrorDetails(): bool
    {
        return $this->displayErrorDetails;
    }

    /**
     * @param bool $displayErrorDetails
     */
    public function setDisplayErrorDetails(bool $displayErrorDetails)
    {
        $this->displayErrorDetails = $displayErrorDetails;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param \Throwable $error
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        \Throwable $error
    ): ResponseInterface {
        $this->reporter->report($error);

        $contentType = $this->determineContentType(
            (string)$request->getHeaderLine('Accept'),
            $this->renderer->getKnownContentTypes()
        );

        ob_start();
        $renderedBody = $this->renderer->render($error, $this->displayErrorDetails, $contentType);
        $outputBuffer = ob_get_clean();
        $renderedBody = $renderedBody ??  $outputBuffer;

        $body = new Body(fopen('php://temp', 'r+'));
        $body->write($renderedBody);
        $response = $response->withStatus(200)->withBody($body);
        if ($error instanceof HttpException) {
            $response = $response->withStatus($error->getStatusCode(), $error->getStatusReasonPhrase());
            foreach ($error->getHeaders() as $header => $value) {
                $response = $response->withHeader($header, $value);
            }
        } else {
            $response = $response->withStatus(500, 'Internal Server Error');
        }
        return $response->withHeader('Content-Type', $contentType);
    }
}
