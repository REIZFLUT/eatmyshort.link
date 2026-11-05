<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Domain;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Domain>
 */
final class DomainType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('hostname', TextType::class, [
                'label' => 'domain.hostname',
                'help' => 'domain.hostname_help',
                'attr' => ['placeholder' => 'go.example.com', 'autocapitalize' => 'off', 'spellcheck' => 'false'],
            ])
            ->add('active', CheckboxType::class, ['label' => 'domain.active', 'required' => false])
            ->add('isDefault', CheckboxType::class, ['label' => 'domain.is_default', 'required' => false])
            ->add('rootRedirectUrl', UrlType::class, [
                'label' => 'domain.root_redirect_url',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'domain.root_redirect_help',
            ])
            ->add('notFoundUrl', UrlType::class, [
                'label' => 'domain.not_found_url',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'domain.not_found_help',
            ])
            ->add('forwardUnknownPaths', CheckboxType::class, [
                'label' => 'domain.forward_unknown_paths',
                'required' => false,
                'help' => 'domain.forward_unknown_paths_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Domain::class]);
    }
}
