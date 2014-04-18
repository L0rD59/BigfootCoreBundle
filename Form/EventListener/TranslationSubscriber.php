<?php

namespace Bigfoot\Bundle\CoreBundle\Form\EventListener;

use Doctrine\Common\Annotations\Reader;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class TranslationSubscriber
 * @package Bigfoot\Bundle\CoreBundle\Form\EventListener
 */
class TranslationSubscriber implements EventSubscriberInterface
{
    /** @var array */
    protected $localeList;
    /** @var \Symfony\Bridge\Doctrine\RegistryInterface */
    protected $doctrine;
    /** @var \Doctrine\Common\Annotations\Reader */
    protected $annotationReader;
    /** @var string */
    protected $defaultLocale;
    /** @var string */
    protected $currentLocale;

    /**
     * @param array             $localeList
     * @param RegistryInterface $doctrine
     * @param Reader            $annotationReader
     * @param string            $defaultLocale
     */
    public function __construct($localeList, RegistryInterface $doctrine, Reader $annotationReader, $defaultLocale)
    {
        $this->localeList       = $localeList;
        $this->doctrine         = $doctrine;
        $this->annotationReader = $annotationReader;
        $this->defaultLocale    = $defaultLocale;
    }

    /**
     * @param string $locale
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->currentLocale = $locale;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->currentLocale ?: $this->defaultLocale;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(FormEvents::PRE_SET_DATA => 'preSetData', FormEvents::POST_SUBMIT => array('postSubmit', -500));
    }

    /**
     * @param FormEvent $event
     * @throws \Exception
     */
    public function preSetData(FormEvent $event)
    {
        try {
            $em                 = $this->doctrine->getManager();
            $defaultLocale      = $this->defaultLocale;
            $locales            = $this->localeList;
            $form               = $event->getForm();
            $parentForm         = $form->getParent();
            $parentData         = $parentForm->getData();
            $entityClass        = get_class($parentData);
            $translatableFields = $this->getTranslatableFields($entityClass);
            $propertyAccessor   = PropertyAccess::createPropertyAccessor();

            $translations = array();
            if ($parentData and method_exists($parentData, 'getId') and $parentData->getId()) {
                /** @var TranslationRepository $repository */
                $repository   = $em->getRepository('Gedmo\\Translatable\\Entity\\Translation');
                $translations = $repository->findTranslations($parentData);

                $defaultLocaleValues = array();
                $parentData->setTranslatableLocale($defaultLocale);
                $em->refresh($parentData);
                foreach ($translatableFields as $fieldName => $fieldType) {
                    $defaultLocaleValues[$fieldName] = $propertyAccessor->getValue($parentData, $fieldName);
                }
                $parentData->setTranslatableLocale($this->getLocale());
                $em->refresh($parentData);

                $translations[$defaultLocale] = $defaultLocaleValues;
            }

            unset($locales[$this->getLocale()]);
            foreach ($locales as $locale => $localeConfig) {
                foreach ($translatableFields as $fieldName => $fieldType) {
                    $data = '';
                    if (isset($translations[$locale][$fieldName])) {
                        $data = $translations[$locale][$fieldName];
                    }

                    if ($parentForm->has($fieldName)) {
                        $fieldType = $parentForm->get($fieldName)->getConfig()->getType()->getInnerType();
                        $fieldAttr = $parentForm->get($fieldName)->getConfig()->getOption('attr');
                        $form->add(sprintf("%s-%s", $fieldName, $locale), $fieldType, array('data' => $data, 'required' => false, 'attr' => array_merge($fieldAttr, array('data-field-name' => $fieldName, 'data-locale' => $locale))));
                    }
                }
            }
        } catch (\Exception $e) {
            $secondException = new \Exception("The object that was given to the form you wanted to translate isn't an entity one. Untranslatable in this case.", $e->getCode(), $e);
            throw $secondException;
        }
    }

    /**
     * @param FormEvent $event
     * @throws \Exception
     */
    public function postSubmit(FormEvent $event)
    {
        try {
            $form       = $event->getForm();
            $parentForm = $form->getParent();
            $parentData = $parentForm->getData();

            if ($parentData) {
                $entityClass        = get_class($parentData);
                $em                 = $this->doctrine->getManagerForClass($entityClass);
                /** @var TranslationRepository $repository */
                $repository         = $em->getRepository('Gedmo\\Translatable\\Entity\\Translation');
                $translatableFields = $this->getTranslatableFields($entityClass);
                $data               = $event->getData();
                $locales            = $this->localeList;

                foreach ($locales as $locale => $localeConf) {
                    foreach ($translatableFields as $field => $type) {
                        $fieldData = '';
                        if (isset($data[$field])) {
                            $fieldData = $data[$field];
                        } elseif (isset($data[$field."-".$locale])) {
                            $fieldData = $data[$field."-".$locale];
                        }
                        $repository->translate($parentData, $field, $locale, $fieldData);
                    }
                }
            }
        } catch (\Exception $e) {
            $secondException = new \Exception("The object that was given to the form you wanted to translate isn't an entity one. Untranslatable in this case.", $e->getCode(), $e);
            throw $secondException;
        }
    }

    /**
     * Returns an array containing all attributes from the given entity and all its eventual inherited parent entities
     * for which a Gedmo\Translatable annotation is set
     *
     * @param string $className
     * @return array
     */
    private function getTranslatableFields($className)
    {
        $reflectionClass = new \ReflectionClass($className);
        $translatableFields = array();

        do {
            $translatableFields = array_merge($translatableFields, $this->getTranslatableFieldsFromClass($reflectionClass));
        } while ($reflectionClass = $reflectionClass->getParentClass());

        return $translatableFields;
    }

    /**
     * Returns an array containing all attributes from the given entity
     * for which a Gedmo\Translatable annotation is set
     *
     * If the given class name is not an entity, returns an empty array
     *
     * @param \ReflectionClass $reflectionClass
     * @return array
     */
    private function getTranslatableFieldsFromClass(\ReflectionClass $reflectionClass)
    {
        $translatableFields = array();

        if ($this->annotationReader->getClassAnnotation($reflectionClass, 'Doctrine\\ORM\\Mapping\\Entity')) {
            $reflectionProperties = $reflectionClass->getProperties();
            foreach ($reflectionProperties as $reflectionProperty) {
                $propertyAnnotation = $this->annotationReader->getPropertyAnnotation($reflectionProperty, 'Gedmo\Mapping\Annotation\Translatable');
                if ($propertyAnnotation) {
                    $mappingAnnotation = $this->annotationReader->getPropertyAnnotation($reflectionProperty, 'Doctrine\ORM\Mapping\Column');
                    $translatableFields[$reflectionProperty->getName()] = $mappingAnnotation->type;
                }
            }
        }

        return $translatableFields;
    }
}
