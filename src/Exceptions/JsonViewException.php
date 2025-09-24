<?php

namespace Craftile\Laravel\Exceptions;

use ErrorException;
use Illuminate\Container\Container;
use Illuminate\Support\Reflector;

class JsonViewException extends ErrorException
{
    /**
     * The path to the JSON template file.
     */
    protected string $templatePath;

    /**
     * Create a new JSON view exception instance.
     */
    public function __construct(string $message, string $templatePath = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, 1, '', 0, $previous);

        $this->templatePath = $templatePath;
    }

    /**
     * Get the template path that caused the exception.
     */
    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    /**
     * Report the exception.
     *
     * @return bool|null
     */
    public function report()
    {
        $exception = $this->getPrevious();

        if (Reflector::isCallable($reportCallable = [$exception, 'report'])) {
            return Container::getInstance()->call($reportCallable);
        }

        return false;
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|null
     */
    public function render($request)
    {
        $exception = $this->getPrevious();

        if ($exception && method_exists($exception, 'render')) {
            return $exception->render($request);
        }

        return null;
    }
}
