<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\NodeAbstract;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\Node\Stmt;
use PhpParser\Node\Scalar;
use RuntimeException;
use SplFileInfo;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Helper\Util\Collector\ClassDefinition;
use TrayDigita\Streak\Source\Helper\Util\Collector\Declaration;
use TrayDigita\Streak\Source\Helper\Util\Collector\Declares;
use TrayDigita\Streak\Source\Helper\Util\Collector\Extend;
use TrayDigita\Streak\Source\Helper\Util\Collector\Implement;
use TrayDigita\Streak\Source\Helper\Util\Collector\Implementations;
use TrayDigita\Streak\Source\Helper\Util\Collector\Import;
use TrayDigita\Streak\Source\Helper\Util\Collector\Imports;
use TrayDigita\Streak\Source\Helper\Util\Collector\Method;
use TrayDigita\Streak\Source\Helper\Util\Collector\Methods;
use TrayDigita\Streak\Source\Helper\Util\Collector\Properties;
use TrayDigita\Streak\Source\Helper\Util\Collector\Property;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

class ObjectFileReader extends AbstractContainerization
{
    use TranslationMethods;

    /**
     * @param string $file
     *
     * @return ClassDefinition
     */
    public function fromFile(string $file): ClassDefinition
    {
        $info = new SplFileInfo($file);
        $path = $info->getRealPath();
        if (!$path) {
            throw new RuntimeException(
                sprintf(
                    $this->translate('%s is not exist.'),
                    $path
                )
            );
        }
        if (!$info->isFile()) {
            throw new RuntimeException(
                sprintf(
                    $this->translate('%s is not a valid file.'),
                    $path
                )
            );
        }

        $stream = StreamCreator::createStream($path, 'r');
        $node = (string) $stream;
        $stream->close();
        unset($stream);
        clearstatcache(true, $path);
        if (trim($node) === '') {
            throw new RuntimeException(
                sprintf(
                    $this->translate('File %s contains empty data.'),
                    $path
                )
            );
        }

        return $this->fromString($node);
    }

    /**
     * @param string $node
     *
     * @return ClassDefinition
     */
    public function fromString(string $node): ClassDefinition
    {
        static $definedConstant;
        if (!$definedConstant) {
            $definedConstant = get_defined_constants(false);
            /*
            $const = array_change_key_case(get_defined_constants(false), CASE_LOWER);
            $definedConstant = [];
            foreach ($const as $item) {
                $definedConstant = array_merge(
                    $definedConstant,
                    array_change_key_case($item, CASE_UPPER)
                );
            }*/
        }

        if (trim($node) === '') {
            throw new InvalidArgumentException(
                $this->translate(
                    'Argument is empty data or whitespace only.'
                )
            );
        }

        $definitions = [
            'name'        => null,
            'namespace'   => null,
            'fullName'   => null,
            'isAnonymous'     => false,
            'isChild'     => false,
            'isFinal'     => false,
            'isAbstract'  => false,
            'isInterface' => false,
            'isTrait'  => false,
            'hasParent' => false,
            'hasInterface' => false,
            'constructor'   => null,
            'declares'    => [],
            'imports' => [],
            'implements' => [],
            'extend' => null,
            'properties' => [],
            'methods' => [],
        ];
        $allAliases = [];
        $allAliasesBase = [];
        $finder = new NodeFinder();
        (new NodeFinder())->find((new ParserFactory)
            ->create(ParserFactory::PREFER_PHP7)
            ->parse($node, new Throwing()), function (NodeAbstract $stmt) use (
                &$finder,
                &$definitions,
                &$allAliases,
                &$allAliasesBase,
                $definedConstant
            ) {
                preg_match('/^([a-zA-Z]+)_([A-Za-z]+)$/', $stmt->getType(), $match);
                if (!($match[2]??null)) {
                    return;
                }
                switch ($match[2]) {
                    case 'Property':
                        /**
                         * @var Stmt\Property $stmt
                         */
                        foreach ($stmt->props as $prop) {
                            $prop->type = $stmt->type;
                            $visibility = 'public';
                            if ($stmt->isPrivate()) {
                                $visibility = 'private';
                            } elseif ($stmt->isProtected()) {
                                $visibility = 'protected';
                            }
                            $ref = $this->paramsCreate($prop);
                            $ref = [
                            ...$ref,
                            'visibility' => $visibility,
                            'isPublic' => $stmt->isPublic(),
                            'isPrivate' => $stmt->isPrivate(),
                            'isProtected' => $stmt->isProtected(),
                            'isReadonly' => $stmt->isReadonly(),
                            'isStatic' => $stmt->isStatic(),
                            ];
                            $definitions['properties'][$ref['name']] = new Property(...$ref);
                        }
                        return;
                    case 'DeclareDeclare':
                        /**
                         * @var Stmt\DeclareDeclare $stmt
                         */
                        preg_match('/_([A-Za-z]+)$/', $stmt->value->getType(), $match);
                        $type = $match[1]??null;
                        if ($type) {
                            $declaration = [
                            'name' => $stmt->key->name,
                            'value' => null
                            ];
                            $value =& $declaration['value'];
                            switch ($type) {
                                case 'Encapsed':
                                    /**
                                     * @var Scalar\Encapsed $stmt
                                     */
                                    $finder->find(
                                        $stmt->parts,
                                        function (Scalar\EncapsedStringPart $part) use (&$value) {
                                            $value .= '\\'.$part->value;
                                        }
                                    );
                                    break;
                                case 'LNumber':
                                case 'DNumber':
                                case 'String':
                                case 'EncapsedStringPart':
                                    /**
                                     * @var Scalar\LNumber|Scalar\DNumber|Scalar\String_|Scalar\EncapsedStringPart $stmt
                                     */
                                    $value = $stmt->value;
                                    if (is_object($value)) {
                                        $value = $value->value;
                                    }
                                    break;
                            }
                            unset($value);
                            $declaration = new Declaration(...$declaration);
                            $definitions['declares'][] = $declaration;
                        }
                        return;
                    case 'Namespace':
                        /**
                         * @var Stmt\Namespace_ $stmt
                         */
                        $definitions['namespace'] = $stmt->name->toString();
                        return;
                    case 'ClassMethod':
                        /**
                         * @var Stmt\ClassMethod $stmt
                         */
                        $methods =& $definitions['methods'];
                        $params = [];
                        $name = $stmt->name->toString();
                        /**
                         * @var Param $param
                         */
                        foreach ($stmt->getParams() as $param) {
                            $paramName = $param->var->name;
                            $params[$paramName] = $this->paramsCreate($param);
                        }

                        $returnType = $stmt?->returnType;
                        $returnType = $returnType?->types??($returnType?->type??null);
                        $returnTypes = [];
                        if ($returnType) {
                            $returnType = !is_array($returnType) ? [$returnType] : $returnType;
                            foreach ($returnType as $returnTypeName) {
                                $returnTypeName = (string) $returnTypeName;
                                $returnTypes[$returnTypeName] = $allAliasesBase[strtolower($returnTypeName)]??(
                                $returnTypeName === 'bool' ? 'boolean' : $returnTypeName
                                    );
                            }
                        }
                        $visibility = 'public';
                        if ($stmt->isPrivate()) {
                            $visibility = 'private';
                        } elseif ($stmt->isProtected()) {
                            $visibility = 'protected';
                        }

                        $current = [
                        'name' => $name,
                        'visibility' => $visibility,
                        'isPublic' => $stmt->isPublic(),
                        'isPrivate' => $stmt->isPrivate(),
                        'isProtected' => $stmt->isProtected(),
                        'isMagicMethod' => $stmt->isMagic(),
                        'isAbstract' => $stmt->isAbstract(),
                        'isFinal' => $stmt->isFinal(),
                        'isStatic' => $stmt->isStatic(),
                        'hasReturnType' => !empty($returnTypes),
                        'returnType' => $returnTypes,
                        'parameters' => $params,
                        ];
                        $methods[strtolower($name)] = new Method(...$current);
                        if (strtolower($name) === '__construct') {
                            $definitions['constructor'] = $name;
                        }
                        return;
                    case 'UseUse':
                        /**
                         * @var Stmt\UseUse $stmt
                         */
                        $object = $stmt->name->toString();
                        $alias = $stmt?->alias?->name;
                        if ($alias) {
                            $lowerAlias = strtolower($alias);
                            $allAliases[$lowerAlias] = $object;
                            /** @noinspection PhpArrayUsedOnlyForWriteInspection */
                            $allAliasesBase[$lowerAlias] = $object;
                        }
                        $last = explode('\\', $object);
                        $last = array_pop($last);
                        if (!in_array(strtolower($object), array_map('strtolower', $allAliases))) {
                            $allAliasesBase[strtolower($last)] = $object;
                        }
                        /** @noinspection PhpArrayUsedOnlyForWriteInspection */
                        $definitions['imports'][$last] = new Import(
                            $last,
                            $object,
                            $alias?:null
                        );
                        return;
                    case 'Class':
                    case 'Interface':
                    case 'Trait':
                        /**
                         * @var Stmt\ClassLike $stmt
                         */
                        /**
                         * @var Stmt\ClassLike $stmt
                         * @var Stmt\Interface_ $stmt
                         * @var Stmt\Trait_ $stmt
                         */
                        $definitions['name'] = $stmt->name?->name;
                        if ($match[2] === 'Class') {
                            /**
                             * @var Stmt\Class_ $stmt
                             */
                            $definitions['isFinal'] = $stmt->isFinal();
                            $definitions['isAbstract'] = $stmt->isAbstract();
                            $definitions['isAnonymous'] = $stmt->isAnonymous();
                        }
                        if ($match[2] === 'Interface') {
                            $definitions['isInterface'] = true;
                        }
                        if ($match[2] === 'Trait') {
                            $definitions['isTrait'] = true;
                        }
                        if (isset($stmt->extends)) {
                            $extend = $stmt->extends->toString();
                            $definitions['hasParent'] = true;
                            $definitions['isChild']   = true;
                            $extendLower = strtolower($extend);
                            if ($stmt->extends instanceof Name\FullyQualified) {
                                $extends = [
                                'fullName' => $stmt->extends->toString(),
                                'name' => $stmt->extends->toString(),
                                'alias' => null
                                ];
                            } elseif (isset($allAliases[$extendLower])) {
                                $extends= [
                                'fullName' => $allAliases[$extendLower],
                                'name' =>  $extend,
                                'alias' => ltrim(strrchr($allAliasesBase[$extendLower], '\\'), '\\')
                                ];
                                $definitions['extend']['alias'] = $extend;
                            } elseif (isset($allAliasesBase[$extendLower])) {
                                $extends= [
                                'fullName' => $allAliasesBase[$extendLower],
                                'name' =>  $extend,
                                'alias' => ltrim(strrchr($allAliasesBase[$extendLower], '\\'), '\\')
                                ];
                            } elseif ($definitions['namespace']) {
                                $extends= [
                                'fullName' =>  sprintf('%s\%s', $definitions['namespace'], $extend),
                                'name' => $extend,
                                'alias' => ltrim(strrchr($allAliasesBase[$extendLower], '\\'), '\\')
                                ];
                            } else {
                                $extends = [
                                'name' => $stmt->extends->toString(),
                                'alias' => null
                                ];
                            }
                            $definitions['extend'] = new Extend(...$extends);
                        }

                        if (isset($stmt->implements)) {
                            foreach ($stmt->implements as $i) {
                                $definitions['hasInterface'] = true;
                                /**
                                 * @var Name $i
                                 */
                                $ie = $i->toString();
                                $val = [
                                'name' => $ie,
                                'alias' => null,
                                ];
                                $ieLower = strtolower($ie);
                                if (isset($allAliases[$ieLower])) {
                                    $val['alias'] = $allAliases[$ieLower];
                                    $val['name'] = $ie;
                                }
                                $definitions['implements'][$val['name']] = new Implement(
                                    $val['name'],
                                    $val['alias']?:null
                                );
                            }
                        }
                        return;
                }
            });

        return new ClassDefinition(
            name: $definitions['name'],
            fullName: $definitions['namespace']
                ? "{$definitions['namespace']}\\{$definitions['name']}"
                : $definitions['name'],
            namespace: $definitions['namespace'],
            isAnonymous: $definitions['isAnonymous'],
            isFinal: $definitions['isFinal'],
            isAbstract: $definitions['isAbstract'],
            isInterface: $definitions['isInterface'],
            isTrait: $definitions['isTrait'],
            constructor: $definitions['constructor']?:null,
            declares: new Declares(...$definitions['declares']),
            imports: new Imports(...$definitions['imports']),
            implementations: new Implementations(...$definitions['implements']),
            extend: $definitions['extend']?:null,
            properties: new Properties(...$definitions['properties']),
            methods: new Methods(...$definitions['methods'])
        );
    }

    #[ArrayShape([
        'name' => "mixed",
        'nullable' => "bool",
        'required' => "bool",
        'hasDefaultValue' => "bool",
        'hasType' => "bool",
        'defaultValue' => "array",
        'type' => "array"
    ])] private function paramsCreate($param): array
    {
        $paramDefaultType = null;
        $paramDefaultName = null;
        $paramDefaultValue = null;
        $paramDefaultAlias = null;
        $isConstant = false;
        if ($param->default) {
            $paramDefault = $param->default;
            $isConstant = (bool) preg_match(
                '~\\\(?:[a-z]+)?const~i',
                get_class($paramDefault)
            );
            if (isset($paramDefault->value)) {
                /**
                 * @var Scalar $paramDefault
                 */
                $paramDefaultType  = gettype($paramDefault->value);
                $paramDefaultValue = $paramDefault->value;
                $paramDefaultName  = $paramDefaultType;
            } elseif ($isConstant) {
                /**
                 * @var Expr $paramDefault
                 */
                $values = [];
                $paramDefaultType = 'constant';
                if (get_class($paramDefault) === ConstFetch::class) {
                    /**
                     * @var ConstFetch $paramDefault
                     */
                    $paramDefaultName = $paramDefault->name->toString();
                    $paramDefaultAlias = $paramDefaultName;
                    $val = strtoupper($paramDefault->name->toLowerString());
                    if (isset($definedConstant[$val])) {
                        $paramDefaultValue = $definedConstant[$val];
                        $paramDefaultType  = gettype($paramDefaultValue);
                        $paramDefaultAlias = $val;
                    }
                } else {
                    foreach ($paramDefault->getSubNodeNames() as $sub) {
                        $values[$sub] = $paramDefault->$sub->toString();
                    }
                    $realValues = implode('::', $values);
                    $paramDefaultName = $realValues;
                    $paramDefaultAlias = $paramDefaultName;
                    if (isset($values['class'])) {
                        $lowerClass = strtolower($values['class']);
                        $paramDefaultAliasName = $allAliasesBase[$lowerClass] ?? $values['class'];
                        $paramDefaultAlias = "$paramDefaultAliasName::{$values['name']}";
                    }
                    $paramDefaultValue = $paramDefaultName;
                }
            } else {
                foreach ($paramDefault->getSubNodeNames() as $key) {
                    $val = $paramDefault->$key;
                    $paramDefaultValue = $val;
                    $paramDefaultType = gettype($val);
                    $paramDefaultName = $paramDefaultType;
                }
            }
        }
        $paramType = $param->type??null;
        $paramTypeType = $paramType?->types??$paramType?->type??null;
        $paramTypeTypes = [];
        if ($paramTypeType) {
            $paramTypeType = !is_array($paramTypeType) ? [$paramTypeType] : $paramTypeType;
            foreach ($paramTypeType as $paramTypeName) {
                $paramTypeName = (string) $paramTypeName;
                $paramTypeTypes[$paramTypeName] = $allAliasesBase[strtolower($paramTypeName)]??(
                    $paramTypeName === 'bool' ? 'boolean' : $paramTypeName
                    );
            }
        }
        $paramNullable = true;
        $paramTypeName = $param?->var->name??$param?->name->name;
        if (!$paramType && isset($param->var)) {
            $paramTypeName = $param->var?->name??$paramTypeName;
        }
        return [
            'name' => $paramTypeName,
            'nullable' => $paramNullable,
            'required' => !((bool) $param->default),
            'hasDefaultValue' => (bool) $param->default,
            'hasType' => !empty($paramTypeTypes),
            'defaultValue' => [
                'type' => $paramDefaultType,
                'name' => $paramDefaultName,
                'value' => $paramDefaultValue,
                'asAlias' => $paramDefaultAlias,
                'isConstant' => $isConstant,
            ],
            'type' => [
                'types' => $paramTypeTypes,
                'hasCondition' => (bool) $paramType,
                'isIdentifier' => $paramType && isset($paramType->name),
            ],
        ];
    }
}
