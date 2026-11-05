<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\EventListener\LocaleListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, ['label' => 'user.display_name'])
            ->add('locale', ChoiceType::class, [
                'label' => 'user.locale',
                'required' => false,
                'placeholder' => 'user.locale_browser',
                'choices' => array_combine(array_map(static fn (string $l): string => 'locale.'.$l, LocaleListener::LOCALES), LocaleListener::LOCALES),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
