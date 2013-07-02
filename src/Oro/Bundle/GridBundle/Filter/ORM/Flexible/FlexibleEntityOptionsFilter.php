<?php

namespace Oro\Bundle\GridBundle\Filter\ORM\Flexible;

use Oro\Bundle\GridBundle\Datagrid\ProxyQueryInterface;

/**
 * Create flexible filter for entity values linked to attribute
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/MIT MIT
 *
 */
class FlexibleEntityOptionsFilter extends AbstractFlexibleFilter
{
    /**
     * FQCN of the linked entity
     *
     * @var string
     */
    protected $className;

    /**
     *
     * @var unknown_type
     */
    protected $parentFilterClass = 'Oro\\Bundle\\GridBundle\\Filter\\ORM\\EntityFilter';

    /**
     * The attribute defining the entity linked
     *
     * @var \Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttribute
     */
    protected $attribute;

    /**
     * {@inheritdoc}
     */
    public function initialize($name, array $options = array())
    {
        parent::initialize($name, $options);

        $this->getAttribute($this->getOption('field_name'));
        $this->getClassName($this->attribute->getBackendType());
        $this->setOption('class', $this->className);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(\Sonata\AdminBundle\Datagrid\ProxyQueryInterface $proxyQuery, $alias, $field, $data)
    {
        $data = $this->parentFilter->parseData($data);
        if (!$data) {
            return;
        }

        $operator = $this->parentFilter->getOperator($data['type']);

        // apply filter
        $this->applyFlexibleFilter($proxyQuery, $field, $data['value'], $operator);
    }

    /**
     * {@inheritdoc}
     */
    public function getValueOptions()
    {
        if (null === $this->valueOptions) {
            $entityManager    = $this->getFlexibleManager()->getStorageManager();
            $entityRepository = $entityManager->getRepository(
                $this->getClassName($this->attribute->getBackendType())
            );
            $entities         = $entityRepository->findAll();

            $this->valueOptions = array();
            foreach ($entities as $entity) {
                $this->valueOptions[$entity->getId()] = $entity->__toString();
            }
        }

        return $this->valueOptions;
    }

    /**
     * Get the attribute linked to the flexible entity filter
     *
     * @return \Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttribute
     */
    protected function getAttribute($attributeCode)
    {
        if ($this->attribute === null) {
            $attribute = $this->getFlexibleManager()
                              ->getAttributeRepository()
                              ->findOneBy(array('code' => $attributeCode));

            if (!$attribute) {
                throw new \LogicException('Impossible to find attribute');
            }

            $this->attribute = $attribute;
        }

        return $this->attribute;
    }

    /**
     * Get the class name of the entity linked
     *
     * @param string $backendType
     *
     * @return string
     *
     * @throws \LogicException
     */
    protected function getClassName($backendType)
    {
        if ($this->className === null) {
            $valueName = $this->flexibleManager->getFlexibleValueName();
            $valueMetadata = $this->flexibleManager->getStorageManager()
                                                   ->getMetadataFactory()
                                                   ->getMetadataFor($valueName);
            $associationMapping = $valueMetadata->getAssociationMappings();

            if (empty($associationMapping[$backendType])
                || empty($associationMapping[$backendType]['targetEntity'])) {
                throw new \LogicException(sprintf('Impossible to find metadata for %s', $backendType));
            }

            $this->className = $associationMapping[$backendType]['targetEntity'];
        }

        return $this->className;
    }

    /**
     * {@inheritdoc}
     */
    protected function applyFlexibleFilter(ProxyQueryInterface $proxyQuery, $field, $value, $operator)
    {
        $attribute = $this->getAttribute($field);
        $qb = $proxyQuery->getQueryBuilder();

        // inner join to value
        $joinAlias = 'filter'.$field;
        $condition = $qb->prepareAttributeJoinCondition($attribute, $joinAlias);
        $qb->innerJoin($qb->getRootAlias() .'.'. $attribute->getBackendStorage(), $joinAlias, 'WITH', $condition);

        // then join to linked entity with filter on id
        $joinAliasEntity = 'filterentity'.$field;
        $backendField = sprintf('%s.id', $joinAliasEntity);
        $condition = $qb->prepareCriteriaCondition($backendField, $operator, $value);
        $qb->innerJoin($joinAlias .'.'. $attribute->getBackendType(), $joinAliasEntity, 'WITH', $condition);

        // filter is active since it's applied to the flexible repository
        $this->active = true;
    }
}
