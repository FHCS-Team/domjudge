<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class CustomProblemPackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Problem Name',
                'required' => false,
                'help' => 'Optional: Override the problem name from config.json',
                'attr' => [
                    'placeholder' => 'e.g., Database Query Optimization',
                ],
            ])
            ->add('externalId', TextType::class, [
                'label' => 'External ID',
                'required' => false,
                'help' => 'Optional: Unique identifier for this problem',
                'attr' => [
                    'placeholder' => 'e.g., db-opt-001',
                ],
            ])
            ->add('timeLimit', TextType::class, [
                'label' => 'Time Limit (seconds)',
                'required' => false,
                'help' => 'Optional: Override evaluation timeout',
                'attr' => [
                    'placeholder' => 'e.g., 120',
                    'type' => 'number',
                    'step' => '1',
                    'min' => '1',
                ],
            ])
            ->add('package', FileType::class, [
                'label' => 'Problem Package (ZIP)',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please upload a problem package',
                    ]),
                    new File([
                        'maxSize' => '100M',
                        'mimeTypes' => [
                            'application/zip',
                            'application/x-zip-compressed',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid ZIP file',
                    ]),
                ],
                'help' => 'ZIP file containing config.json, Dockerfiles, and evaluation scripts',
                'attr' => [
                    'accept' => '.zip',
                ],
            ])
            ->add('upload', SubmitType::class, [
                'label' => 'Upload Problem Package',
                'attr' => [
                    'class' => 'btn btn-primary btn-lg',
                ],
            ]);
    }
}
