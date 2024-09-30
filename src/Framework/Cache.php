<?php

namespace Tunnela\TremedCodingChallenge\Framework;

class Cache
{
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function put($name, $content) 
    {
        $path = $this->path . '/' . $this->escape($name);

        return file_put_contents($path, serialize($content));
    }

    public function has($name, $seconds = null) 
    {
        $path = $this->path . '/' . $this->escape($name);

        return file_exists($path) && ($seconds === null || (time() - filemtime($path)) < $seconds);
    }

    public function get($name, $seconds = null) 
    {
        $path = $this->path . '/' . $this->escape($name);

        if ($this->has($name, $seconds)) {
            return unserialize(file_get_contents($path));
        }
        return null;
    }

    public function getOrPut($name, $update = null, $seconds = null) 
    {
        $path = $this->path . '/' . $this->escape($name);

        if ($this->has($name, $seconds)) {
            return unserialize(file_get_contents($path));
        }
        if ($update instanceof \Closure) {
            $content = $update();

            $this->put($name, $content);

            return $content;
        }
        return null;
    }

    public function forget($name) 
    {
        @unlink($this->path . '/' . $this->escape($name));
    }

    protected function escape($name) 
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
    }
}
