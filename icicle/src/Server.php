<?php
/**
 * @author Adam Englander <adam@launchkey.com>
 * @copyright 2016 LaunchKey, Inc. See project license for usage.
 */

namespace aenglander\IoT\Example;


use Icicle\Http\Exception\MessageException;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Request;
use Icicle\File;
use Icicle\Http\Server\RequestHandler;
use Icicle\Loop;
use Icicle\Socket\Socket;
use Icicle\Stream\MemorySink;

class Server implements RequestHandler
{
    const ACCEPTABLE_TYPES = ['text/json', 'application/json'];

    public function onRequest(Request $request, Socket $socket)
    {
        $path = $request->getUri()->getPath();

        try {
            if (preg_match('/^\/(\d+)$/', $path, $matches)) {
                yield $this->gpioPinHandler($matches[1]);
            } elseif (preg_match('/^\/(\d+)\/(high|low)$/', $path, $matches)) {
                yield $this->gpioPinHandler($matches[1], $matches[2]);
            } elseif (preg_match('/^\/$/', $path)) {
                yield $this->rootHandler();
            } else {
                yield $this->notFoundHandler();
            }
        } catch (MessageException $e) {
            $sink = new MemorySink();
            yield $sink->end($e->getMessage());

            $response = new BasicResponse($e->getResponseCode(), [
                'Content-Type' => 'text/plain',
                'Content-Length' => $sink->getLength(),
            ], $sink);
            yield $response;
        }
    }

    public function onError($code, Socket $socket)
    {
        return new BasicResponse($code);
    }

    public function notFoundHandler() {
        $sink = new MemorySink();
        yield $sink->end("Not Found");

        $response = new BasicResponse(404, [
            'Content-Type' => 'text/plain',
            'Content-Length' => $sink->getLength(),
        ], $sink);

        yield $response;
    }

    public function rootHandler() {
        $sink = new MemorySink();
        yield $sink->end("GPIO with Icicle.io example!
            \n\nAccess the pin number to get the status: /18
            \n\nAccess the pin with high/low to change the state: /18/high");

        $response = new BasicResponse(200, [
            'Content-Type' => 'text/plain',
            'Content-Length' => $sink->getLength(),
        ], $sink);

        yield $response;
    }

    private function gpioPinHandler($pin, $action = null)
    {
        if ($action) {
            if (! (yield File\isDir("/sys/class/gpio/gpio{$pin}"))) {
                $export = (yield File\open("/sys/class/gpio/export", "w"));
                yield $export->write($pin);
                yield $export->close();
            }
            $value = (yield File\open("/sys/class/gpio/gpio{$pin}/value", 'w'));
            yield $value->write($action == 'low' ? 0 : 1);
        }

        $pin_data = (yield $this->getPinData($pin));
        $sink = new MemorySink();
        yield $sink->end(json_encode($pin_data));

        $response = new BasicResponse(200, [
            'Content-Type' => 'application/json',
            'Content-Length' => $sink->getLength(),
        ], $sink);
        yield $response;
    }

    /**
     * @param $pin
     * @return array|\Generator
     */
    private function getPinData($pin)
    {
        $pin_data = ['pin' => $pin];
        if (yield File\isDir("/sys/class/gpio/gpio{$pin}")) {
            $pin_data['initialized'] = true;
            $direction = (yield File\open("/sys/class/gpio/gpio{$pin}/direction", 'r'));
            $pin_data['direction'] = trim(yield $direction->read());
            yield $direction->close();
            $value = (yield File\open("/sys/class/gpio/gpio{$pin}/value", 'r'));
            $pin_data['value'] = trim(yield $value->read()) == "1" ? "high" : "low";
            yield $value->close();
        } else {
            $pin_data['initialized'] = false;
        }
        yield $pin_data;
    }
}
