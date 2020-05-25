<?php

namespace Mpociot\ApiDoc\Extracting\Strategies\ResponseParameters;

use Illuminate\Support\Arr;
use Mpociot\Reflection\DocBlock;
use ReflectionClass;
use ReflectionMethod;

trait CollectTransformerParamsHelper
{
    use FromDocBlockHelper;

    /**
     * Collect @responseParam definitions from a transformer by inspecting its "transform" method (or __invoke)
     *
     * @param ReflectionClass $transformerReflection
     * @return array
     * @throws \ReflectionException
     */
    protected function fromTransformMethod(ReflectionClass $transformerReflection)
    {
        // Reflect the transformer
        $method = 'transform';

        if (!$transformerReflection->hasMethod('transform')) {
            $method = '__invoke';
        }
        if (!$transformerReflection->hasMethod($method)) {
            return null;
        }

        return $this->getResponseParametersFromDocBlock(
            (new DocBlock($transformerReflection->getMethod($method)->getDocComment() ?: ''))->getTags()
        ) ?: [];
    }

    /**
     * Collect @responseParam definitions from includes by looking for @transformer definitions
     *
     * @param ReflectionClass $transformerReflection
     * @return array
     * @throws \ReflectionException
     */
    protected function collectIncludes(ReflectionClass $transformerReflection): array
    {
        $defaultIncludes = $transformerReflection->getProperty('defaultIncludes');
        $defaultIncludes->setAccessible(true);
        $defaultIncludes = $defaultIncludes->getValue($transformerReflection->newInstanceWithoutConstructor());

        $availableIncludes = $transformerReflection->getProperty('availableIncludes');
        $availableIncludes->setAccessible(true);
        $availableIncludes = $availableIncludes->getValue($transformerReflection->newInstanceWithoutConstructor());

        $includes = array_merge($defaultIncludes, $availableIncludes);
        
        if (empty($includes)) {
            return [];
        }

        $includeParams = [];

        foreach ($includes as $method => $key) {
            $candidateMethod = 'include' . ucfirst(is_numeric($method) ? $key : $method);

            if (!$transformerReflection->hasMethod($candidateMethod)) {
                continue;
            }

            $candidateMethod = $transformerReflection->getMethod($candidateMethod);

            [$prefix, $transformerClass] = $this->processIncludeDocBlock($candidateMethod);

            if (!$transformerClass || !class_exists($transformerClass)) {
                continue;
            }

            $params = collect($this->fromTransformMethod(new ReflectionClass($transformerClass)))
                ->keyBy(fn ($_, $existingKey) => $key . '.' . $prefix . $existingKey);

            $includeParams = array_merge($includeParams, $params->toArray());
        }

        return $includeParams;
    }

    /**
     * Attempts to resolve a transformer, and the prefix for how it appears in responses from a docblock on an "include"
     * method
     *
     * @param ReflectionMethod $candidateMethod
     * @return array
     */
    protected function processIncludeDocBlock(ReflectionMethod $candidateMethod)
    {
        $doc = new DocBlock($candidateMethod->getDocComment());

        $tags = [
            '' => 'transformer',
            '*.' => 'transformerCollection',
            'data.*.' => 'transformerCollectionPaginated',
        ];

        foreach ($tags as $prefix => $tag) {
            if (!$doc->hasTag($tag)) {
                continue;
            }

            return [$prefix, Arr::last($doc->getTagsByName($tag))->getContent()];
        }

        return [];
    }
}
