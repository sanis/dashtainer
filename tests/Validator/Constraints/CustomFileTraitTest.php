<?php

namespace Dashtainer\Tests\Validator\Constraints;

use Dashtainer\Tests\Form\CustomFileMock;
use Dashtainer\Validator\Constraints\CustomFileTrait;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class CustomFileTraitTest extends KernelTestCase
{
    /** @var MockObject|ConstraintViolationBuilderInterface */
    protected $constraintBuilder;

    /** @var MockObject|ExecutionContextInterface */
    protected $context;

    /** @var CustomFileMock */
    protected $customFile;

    protected function setUp()
    {
        $this->context = $this->getMockBuilder(ExecutionContextInterface::class)
            ->getMock();

        $this->constraintBuilder = $this->getMockBuilder(
                ConstraintViolationBuilderInterface::class
            )
            ->setConstructorArgs([])
            ->getMock();

        $this->customFile = new CustomFileMock();
    }

    public function testValidateCustomFileFailsOnEmptyFilename()
    {
        $custom_file = [
            [
                'filename' => '',
                'target'   => 'targetData',
            ],
            [
                'filename' => '    ',
                'target'   => 'targetData',
            ],
            [
                'filename' => false,
                'target'   => 'targetData',
            ],
            [
                'filename' => null,
                'target'   => 'targetData',
            ],
        ];

        $this->context->expects($this->exactly(4))
            ->method('buildViolation')
            ->with('Ensure all custom config files have a filename')
            ->willReturn($this->constraintBuilder);

        $this->constraintBuilder->expects($this->exactly(4))
            ->method('atPath')
            ->willReturnSelf();

        $this->constraintBuilder->expects($this->exactly(4))
            ->method('addViolation');

        $this->customFile->custom_file = $custom_file;
        $this->customFile->validate($this->context, null);
    }

    public function testValidateCustomFileFailsOnEmptyTarget()
    {
        $custom_file = [
            [
                'filename' => 'filenameData',
                'target'   => '',
            ],
            [
                'filename' => 'filenameData',
                'target'   => '    ',
            ],
            [
                'filename' => 'filenameData',
                'target'   => false,
            ],
            [
                'filename' => 'filenameData',
                'target'   => null,
            ],
        ];

        $this->context->expects($this->exactly(4))
            ->method('buildViolation')
            ->with('Ensure all custom config files have a target')
            ->willReturn($this->constraintBuilder);

        $this->constraintBuilder->expects($this->exactly(4))
            ->method('atPath')
            ->willReturnSelf();

        $this->constraintBuilder->expects($this->exactly(4))
            ->method('addViolation');

        $this->customFile->custom_file = $custom_file;
        $this->customFile->validate($this->context, null);
    }

    public function testValidateCustomFileFailsOnDupliecateFilename()
    {
        $custom_file = [
            [
                'filename' => 'filenameData1',
                'target'   => 'targetData1',
            ],
            [
                'filename' => 'filenameData2',
                'target'   => 'targetData2',
            ],
            [
                'filename' => 'filenameData1',
                'target'   => 'targetData3',
            ],
            [
                'filename' => 'filenameData4',
                'target'   => 'targetData4',
            ],
        ];

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with('Ensure all custom config filenames are unique')
            ->willReturn($this->constraintBuilder);

        $this->constraintBuilder->expects($this->once())
            ->method('atPath')
            ->willReturnSelf();

        $this->constraintBuilder->expects($this->once())
            ->method('addViolation');

        $this->customFile->custom_file = $custom_file;
        $this->customFile->validate($this->context, null);
    }


    public function testValidateCustomFileFailsOnDupliecateTarget()
    {
        $custom_file = [
            [
                'filename' => 'filenameData1',
                'target'   => 'targetData1',
            ],
            [
                'filename' => 'filenameData2',
                'target'   => 'targetData2',
            ],
            [
                'filename' => 'filenameData3',
                'target'   => 'targetData1',
            ],
            [
                'filename' => 'filenameData4',
                'target'   => 'targetData4',
            ],
        ];

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with('Ensure all custom config targets are unique')
            ->willReturn($this->constraintBuilder);

        $this->constraintBuilder->expects($this->once())
            ->method('atPath')
            ->willReturnSelf();

        $this->constraintBuilder->expects($this->once())
            ->method('addViolation');

        $this->customFile->custom_file = $custom_file;
        $this->customFile->validate($this->context, null);
    }
}
