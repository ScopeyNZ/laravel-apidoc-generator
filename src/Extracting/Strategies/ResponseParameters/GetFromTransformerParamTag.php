<?php

namespace Mpociot\ApiDoc\Extracting\Strategies\ResponseParameters;

use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Mpociot\ApiDoc\Extracting\RouteDocBlocker;
use Mpociot\ApiDoc\Extracting\Strategies\Strategy;
use Mpociot\ApiDoc\Extracting\TransformerHelpers;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionMethod;

class GetFromTransformerParamTag extends Strategy
{
    use CollectTransformerParamsHelper;
    use TransformerHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionMethod $method, array $routeRules, array $context = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];

        $tag = $this->getTransformerTag($methodDocBlock->getTags());

        if (empty($tag)) {
            return null;
        }

        [$statusCode, $transformer] = $this->getStatusCodeAndTransformerClass($tag);
        $reflection = new ReflectionClass($transformer);

        return array_merge($this->fromTransformMethod($reflection), $this->collectIncludes($reflection));
    }


}
