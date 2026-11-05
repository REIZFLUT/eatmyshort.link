<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Domain;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
final class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, ['label' => 'user.display_name'])
            ->add('email', EmailType::class, ['label' => 'user.email'])
            ->add('admin', CheckboxType::class, [
                'label' => 'user.admin',
                'required' => false,
                'help' => 'user.admin_help',
                'disabled' => $options['is_self'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'user.active',
                'required' => false,
                'disabled' => $options['is_self'],
            ])
            ->add('domains', EntityType::class, [
                'label' => 'user.domains',
                'class' => Domain::class,
                'choice_label' => static fn (Domain $domain): string => $domain->getDisplayName(),
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'required' => false,
                'help' => 'user.domains_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'is_self' => false]);
        $resolver->setAllowedTypes('is_self', 'bool');
    }
}
