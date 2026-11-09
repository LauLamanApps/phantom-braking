<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<array{licensePlate: ?string}> */
final class WithdrawMediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('licensePlate', TextType::class, [
            'label' => 'withdraw.plate',
            'help' => 'withdraw.plate.help',
            'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 20)],
            'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'characters', 'class' => 'uppercase tracking-widest font-mono'],
        ]);
    }
}
