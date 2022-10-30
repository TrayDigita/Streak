<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

use InvalidArgumentException;
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
use TrayDigita\Streak\Source\Helper\Util\Collector\ResultParser;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

class ObjectFileReader extends AbstractContainerization
{
    use TranslationMethods;

    /**
     * @param string $file
     *
     * @return ResultParser
     */
    public function fromFile(string $file): ResultParser
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
     * @return ResultParser
     */
    public function fromString(string $node): ResultParser
    {
        static $definedConstant;
        if (!$definedConstant) {
            $const = array_change_key_case(get_defined_constants(true), CASE_LOWER);
            unset($const['user']);
            $definedConstant = [];
            foreach ($const as $item) {
                $definedConstant = array_merge(
                    $definedConstant,
                    array_change_key_case($item, CASE_UPPER)
                );
            }
        }

        if (trim($node) === '') {
            throw new InvalidArgumentException(
                $this->translate(
                    'Argument is empty data or whitespace only.'
                )
            );
        }

        $node = (new ParserFactory)->create(ParserFactory::PREFER_PHP7)
            ->parse($node, new Throwing());
        $finder = new NodeFinder();
        $definitions = [
            'declare' => [],
            'namespace' => null,
            'use' => [],
            'class' => [
                'name'     => null,
                'isChild'  => false,
                'hasParent' => false,
                'hasInterface' => false,
                'hasConstructor' => false,
                'constructor'   => null,
                'parents' => [
                    'extend' => [
                        'name' => null,
                        'asAlias' => null,
                    ],
                    'implements' => [],
                ],
                'type' => [
                    'mode' => null,
                    'name' => null,
                    'final' => false,
                ],
                'methods' => [],
            ],
        ];

        $allAliases = [];
        $allAliasesBase = [];
        $finder->find($node, function (NodeAbstract $stmt) use (
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
                case 'DeclareDeclare':
                    /**
                     * @var Stmt\DeclareDeclare $stmt
                     */
                    preg_match('/_([A-Za-z]+)$/', $stmt->value->getType(), $match);
                    $type = $match[1]??null;
                    if ($type) {
                        $definitions['declare'][$stmt->key->name] = [
                            'name' => $stmt->key->name,
                            'value' => null
                        ];
                        $value =& $definitions['declare'][$stmt->key->name]['value'];
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
                    }
                    return;
                case 'Namespace':
                    /**
                     * @var Stmt\Namespace_ $stmt
                     */
                    $name = $stmt->name;
                    $definitions['namespace'] = $name->toString();
                    return;
                case 'ClassMethod':
                    /**
                     * @var Stmt\ClassMethod $stmt
                     */
                    $methods =& $definitions['class']['methods'];
                    $params = [];
                    $name = $stmt->name->toString();
                    /**
                     * @var Param $param
                     */
                    foreach ($stmt->getParams() as $param) {
                        $paramName = $param->var->name;
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
                                        $paramDefaultType = gettype($paramDefaultValue);
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
                        $paramTypeType = $paramType ? $paramType->type ??$paramType:$paramType;
                        $paramTypeType = $paramTypeType ? $paramTypeType->toString() : $paramTypeType;
                        $paramTypeName = $paramTypeType
                            ? $allAliasesBase[strtolower($paramTypeType)]??$paramTypeType
                            : $paramTypeType;
                        $paramNullable = true;
                        if (!$paramType && isset($param->var)) {
                            $paramTypeType =
                            $paramTypeName = $param->var->name;
                        }

                        $refParam = [
                            'name' => $paramName,
                            'nullable' => $paramNullable,
                            'required' => !((bool) $param->default),
                            'hasDefaultValue' => (bool) $param->default,
                            'hasType' => (bool) $paramTypeType,
                            'defaultValue' => [
                                'type' => $paramDefaultType,
                                'name' => $paramDefaultName,
                                'value' => $paramDefaultValue,
                                'asAlias' => $paramDefaultAlias,
                                'isConstant' => $isConstant,
                            ],
                            'type' => [
                                'name'      => $paramTypeType,
                                'realName'  => $paramTypeName,
                                'hasCondition' => (bool) $paramType,
                                'isIdentifier' => $paramType && isset($paramType->name),
                            ],
                        ];
                        $params[$paramName] = $refParam;
                    }

                    $returnType = $stmt->returnType;
                    $returnType  = $returnType ? $returnType->type ??$returnType:$returnType;
                    $returnTypeType = $returnType ? $returnType->toString() : $returnType;
                    $returnTypeValue = $returnType
                        ? $allAliasesBase[strtolower($returnTypeType)]??$returnTypeType
                        : $returnTypeType;
                    $visibility = 'public';
                    if ($stmt->isPrivate()) {
                        $visibility = 'private';
                    } elseif ($stmt->isProtected()) {
                        $visibility = 'protected';
                    }

                    $methods[strtolower($name)] = [
                        'name' => $name,
                        'visibility' => $visibility,
                        'isPublic' => $stmt->isPublic(),
                        'isPrivate' => $stmt->isPrivate(),
                        'isProtected' => $stmt->isProtected(),
                        'isMagicMethod' => $stmt->isMagic(),
                        'isAbstract' => $stmt->isAbstract(),
                        'isFinal' => $stmt->isFinal(),
                        'isStatic' => $stmt->isStatic(),
                        'hasReturnType' => (bool) $returnType,
                        'returnType' => [
                            'name'      => $returnTypeType,
                            'realName'  => $returnTypeValue,
                        ],
                        'parameters' => $params,
                    ];
                    if (strtolower($name) === '__construct') {
                        $definitions['class']['hasConstructor'] = true;
                        $definitions['class']['constructor'] = $name;
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
                    $definitions['use'][$object] = [
                        'name' => $object,
                        'alias' => $alias,
                        'base' => $last,
                    ];
                    return;
                case 'Class':
                case 'Interface':
                case 'Trait':
                    $parents =& $definitions['class']['parents'];
                    $type =&$definitions['class']['type'];
                    /**
                     * @var Stmt\ClassLike $stmt
                     */
                    /**
                     * @var Stmt\ClassLike $stmt
                     * @var Stmt\Interface_ $stmt
                     * @var Stmt\Trait_ $stmt
                     */
                    $definitions['class']['name'] = $stmt->name?->name;
                    $type['name'] = strtolower($match[2]);
                    $type['mode'] = $type['name'];
                    if ($match[2] === 'Class') {
                        /**
                         * @var Stmt\Class_ $stmt
                         */
                        if ($stmt->isAbstract()) {
                            $type['mode'] = 'abstract';
                        } elseif ($stmt->isAnonymous()) {
                            $type['mode'] = 'anonymous';
                        } else {
                            $type['mode'] = 'class';
                        }
                        $type['final'] = $stmt->isFinal();
                    }

                    if (isset($stmt->extends)) {
                        $extend = $stmt->extends->toString();
                        $definitions['class']['hasParent'] = true;
                        $definitions['class']['isChild']   = true;
                        $parents['extend']['name'] = $extend;
                        $extendLower = strtolower($extend);
                        if (isset($allAliases[$extendLower])) {
                            $parents['extend']['name'] = $allAliases[$extendLower];
                            $parents['extend']['asAlias'] = $extend;
                        } elseif (isset($allAliasesBase[$extendLower])) {
                            $parents['extend']['name'] = $allAliasesBase[$extendLower];
                            $parents['extend']['asAlias'] = $extend;
                        } elseif ($definitions['namespace']) {
                            $parents['extend']['name'] = sprintf('%s\%s', $definitions['namespace'], $extend);
                            $parents['extend']['asAlias'] = $extend;
                        }
                    }
                    if (isset($stmt->implements)) {
                        foreach ($stmt->implements as $i) {
                            $definitions['class']['hasInterface'] = true;
                            /**
                             * @var Name $i
                             */
                            $ie = $i->toString();
                            $val = [
                                'name' => $ie,
                                'asAlias' => null,
                            ];
                            $ieLower = strtolower($ie);
                            if (isset($allAliases[$ieLower])) {
                                $val['asAlias'] = $allAliases[$ieLower];
                                $val['name'] = $ie;
                            }
                            $parents['implements'][$val['name']] = $val;
                        }
                    }
                    return;
            }
        });

        unset($allAliases, $allAliasesBase);
        return new ResultParser(
            $definitions['declare'],
            $definitions['namespace'],
            $definitions['use'],
            $definitions['class']
        );
    }
}
