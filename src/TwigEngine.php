<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Twig;

use Spiral\Twig\Exception\SyntaxException;
use Spiral\Views\ContextInterface;
use Spiral\Views\EngineInterface;
use Spiral\Views\Exception\EngineException;
use Spiral\Views\LoaderInterface;
use Spiral\Views\ProcessorInterface;
use Spiral\Views\ViewInterface;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Extension\ExtensionInterface;
use Twig\TemplateWrapper;

class TwigEngine implements EngineInterface
{
    protected const EXTENSION = 'twig';

    /** @var bool|null|TwigCache */
    private $cache = false;

    /** @var array */
    private $options = [];

    /** @var LoaderInterface|null */
    private $loader = null;

    /** @var Environment|null */
    private $environment = null;

    /** @var ExtensionInterface[] */
    private $extensions = [];

    /** @var ProcessorInterface[] */
    private $processors = [];

    /**
     * @param TwigCache|null $cache
     * @param array          $options
     */
    public function __construct(TwigCache $cache = null, array $options = [])
    {
        $this->cache = $cache ?? false;
        $this->options = $options;
    }

    /**
     * @param ExtensionInterface $extension
     */
    public function addExtension(ExtensionInterface $extension)
    {
        $this->extensions[] = $extension;
    }

    /**
     * @param ProcessorInterface $processor
     */
    public function addProcessor(ProcessorInterface $processor)
    {
        $this->processors[] = $processor;
    }

    /**
     * {@inheritdoc}
     */
    public function withLoader(LoaderInterface $loader): EngineInterface
    {
        $engine = clone $this;
        $engine->loader = $loader->withExtension(static::EXTENSION);

        $engine->environment = new Environment(
            new TwigLoader($engine->loader, $this->processors),
            $this->options
        );

        $engine->environment->setCache($this->cache);
        foreach ($this->extensions as $extension) {
            $engine->environment->addExtension($extension);
        }

        return $engine;
    }

    /**
     * {@inheritdoc}
     */
    public function getLoader(): LoaderInterface
    {
        if (empty($this->loader)) {
            throw new EngineException("No associated loader found.");
        }

        return $this->loader;
    }

    /**
     * Return environment locked to specific context.
     *
     * @param ContextInterface $context
     * @return Environment
     */
    public function getEnvironment(ContextInterface $context): Environment
    {
        if (empty($this->environment)) {
            throw new EngineException("No associated environment found.");
        }

        $this->environment->getLoader()->setContext($context);

        return $this->environment;
    }

    /**
     * @inheritdoc
     */
    public function compile(string $path, ContextInterface $context): TemplateWrapper
    {
        try {
            return $this->getEnvironment($context)->load($this->normalize($path));
        } catch (SyntaxError $exception) {
            //Let's clarify exception location
            throw SyntaxException::fromTwig($exception);
        }
    }

    /**
     * @inheritdoc
     */
    public function reset(string $path, ContextInterface $context)
    {
        $path = $this->normalize($path);
        $this->cache->delete($path, $this->getEnvironment($context)->getTemplateClass($path));
    }

    /**
     * @inheritdoc
     */
    public function get(string $path, ContextInterface $context): ViewInterface
    {
        return new TwigView($this->compile($path, $context));
    }

    /**
     * @param string $path
     * @return string
     */
    protected function normalize(string $path): string
    {
        $path = $this->getLoader()->load($path);

        return sprintf("%s:%s", $path->getNamespace(), $path->getName());
    }
}