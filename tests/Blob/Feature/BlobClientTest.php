<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Feature;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\Exceptions\BlobNotFoundExceptionBlob;
use AzureOss\Storage\Blob\Exceptions\ContainerNotFoundExceptionBlob;
use AzureOss\Storage\Blob\Exceptions\TagsTooLargeException;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Tests\Blob\BlobFeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\StreamInterface;

final class BlobClientTest extends BlobFeatureTestCase
{
    private BlobContainerClient $containerClient;
    private BlobClient $blobClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->containerClient = $this->serviceClient->getContainerClient("blobclient");
        $this->blobClient = $this->containerClient->getBlobClient("some/file.txt");
        $this->cleanContainer($this->containerClient->containerName);
    }

    #[Test]
    public function download_stream_works(): void
    {
        $content = "Lorem ipsum dolor sit amet";
        $this->blobClient->upload($content, new UploadBlobOptions("text/plain"));

        $result = $this->blobClient->downloadStreaming();

        $this->assertEquals($result->properties->contentLength, strlen($content));
        $this->assertEquals("text/plain", $result->properties->contentType);
        $this->assertEquals($content, $result->content->getContents());
    }

    #[Test]
    public function download_streams_throws_if_container_doesnt_exist(): void
    {
        $this->expectException(ContainerNotFoundException::class);

        $this->serviceClient->getContainerClient('noop')->getBlobClient('noop')->downloadStreaming();
    }

    #[Test]
    public function download_stream_throws_if_blob_doesnt_exist(): void
    {
        $this->expectException(BlobNotFoundException::class);

        $this->blobClient->downloadStreaming();
    }

    #[Test]
    public function get_properties_works(): void
    {
        $content = "Lorem ipsum dolor sit amet";
        $this->blobClient->upload($content, new UploadBlobOptions("text/plain"));

        $result = $this->blobClient->getProperties();

        $this->assertEquals($result->contentLength, strlen($content));
        $this->assertEquals("text/plain", $result->contentType);
    }

    #[Test]
    public function get_properties_throws_if_container_doesnt_exist(): void
    {
        $this->expectException(ContainerNotFoundException::class);

        $this->serviceClient->getContainerClient('noop')->getBlobClient('noop')->getProperties();
    }

    #[Test]
    public function get_properties_throws_if_blob_doesnt_exist(): void
    {
        $this->expectException(BlobNotFoundException::class);

        $this->blobClient->getProperties();
    }

    #[Test]
    public function delete_works(): void
    {
        $this->blobClient->upload("test");

        $this->assertTrue($this->blobClient->exists());

        $this->blobClient->delete();

        $this->assertFalse($this->blobClient->exists());
    }

    #[Test]
    public function delete_works_throws_if_container_doesnt_exist(): void
    {
        $this->expectException(ContainerNotFoundException::class);

        $this->serviceClient->getContainerClient('noop')->getBlobClient('noop')->delete();
    }

    #[Test]
    public function delete_works_throws_if_blob_doesnt_exist(): void
    {
        $this->expectException(BlobNotFoundException::class);

        $this->blobClient->deleteIfExists();
        $this->blobClient->delete();
    }

    #[Test]
    public function delete_if_exists_works(): void
    {
        $this->blobClient->upload("test");

        $this->assertTrue($this->blobClient->exists());

        $this->blobClient->deleteIfExists();

        $this->assertFalse($this->blobClient->exists());
    }

    public function delete_if_exists_throws_if_container_doesnt_exist(): void
    {
        $this->expectException(ContainerNotFoundException::class);

        $this->blobClient->deleteIfExists();
    }

    #[Test]
    public function delete_if_exists_doesnt_throws_if_blob_doesnt_exist(): void
    {
        $this->expectNotToPerformAssertions();

        $this->blobClient->deleteIfExists();
    }

    #[Test]
    public function exists_works(): void
    {
        $this->assertFalse($this->blobClient->exists());

        $this->blobClient->upload("test");

        $this->assertTrue($this->blobClient->exists());
    }

    #[Test]
    public function exists_works_throws_if_container_doesnt_exist(): void
    {
        $this->expectException(ContainerNotFoundException::class);

        $this->serviceClient->getContainerClient('noop')->getBlobClient('noop')->exists();
    }

    #[Test]
    public function upload_works_with_single_upload(): void
    {
        $this->withFile(1000, function (StreamInterface $file) {
            $beforeUploadContent = $file->getContents();
            $file->rewind();

            $this->blobClient->upload($file, new UploadBlobOptions("text/plain", initialTransferSize: 2000));

            $properties = $this->blobClient->getProperties();

            $this->assertEquals("text/plain", $properties->contentType);
            $this->assertEquals(1000, $properties->contentLength);

            $afterUploadContent = $this->blobClient->downloadStreaming()->content;

            $this->assertEquals($beforeUploadContent, $afterUploadContent);
        });
    }

    #[Test]
    public function upload_works_with_parallel_upload(): void
    {
        $this->withFile(1000, function (StreamInterface $file) {
            $beforeUploadContent = $file->getContents();
            $file->rewind();

            $this->blobClient->upload($file, new UploadBlobOptions("text/plain", initialTransferSize: 500, maximumTransferSize: 100));

            $properties = $this->blobClient->getProperties();

            $this->assertEquals("text/plain", $properties->contentType);
            $this->assertEquals(1000, $properties->contentLength);

            $afterUploadContent = $this->blobClient->downloadStreaming()->content;

            $this->assertEquals($beforeUploadContent, $afterUploadContent);
        });
    }

    #[Test]
    public function upload_works_with_empty_file(): void
    {
        $this->blobClient->upload("", new UploadBlobOptions("text/plain", initialTransferSize: 500, maximumTransferSize: 100));

        $properties = $this->blobClient->getProperties();

        $this->assertEquals("text/plain", $properties->contentType);
        $this->assertEquals(0, $properties->contentLength);

        $afterUploadContent = $this->blobClient->downloadStreaming()->content;

        $this->assertEquals("", $afterUploadContent);
    }

    #[Test]
    public function upload_throws_if_container_doesnt_exist(): void
    {
        $this->expectException(ContainerNotFoundException::class);

        $this->serviceClient->getContainerClient('noop')->getBlobClient('noop')->upload("test");
    }

    #[Test]
    public function copy_from_url_works(): void
    {
        $sourceContainerClient = $this->serviceClient->getContainerClient($this->randomContainerName());

        $this->cleanContainer($sourceContainerClient->containerName);

        $sourceBlobClient = $sourceContainerClient->getBlobClient("to_copy");
        $sourceBlobClient->upload("This should be copied!");

        $this->blobClient->copyFromUri($sourceBlobClient->uri);

        $sourceContent = $sourceBlobClient->downloadStreaming()->content->getContents();
        $targetContent = $this->blobClient->downloadStreaming()->content->getContents();

        $this->assertEquals($targetContent, $sourceContent);
    }

    #[Test]
    public function copy_from_url_works_throws_if_source_container_doesnt_exist(): void
    {
        $sourceContainerClient = $this->serviceClient->getContainerClient("blobclienttestscopy");
        $sourceContainerClient->deleteIfExists(); // cleanup

        $sourceBlobClient = $sourceContainerClient->getBlobClient("to_copy");
        $this->expectException(ContainerNotFoundException::class);

        $this->blobClient->copyFromUri($sourceBlobClient->uri);
    }

    #[Test]
    public function copy_from_url_works_throws_if_source_blob_doesnt_exist(): void
    {
        $sourceContainerClient = $this->serviceClient->getContainerClient("copyfromurl");

        $this->cleanContainer($sourceContainerClient->containerName);

        $sourceBlobClient = $sourceContainerClient->getBlobClient("to_copy");
        $this->expectException(BlobNotFoundException::class);

        $this->blobClient->copyFromUri($sourceBlobClient->uri);
    }

    #[Test]
    public function generate_sas_uri_works(): void
    {
        $this->expectNotToPerformAssertions();

        $blobClient = $this->containerClient->getBlobClient("blob");
        $blobClient->upload("test");

        $sas = $blobClient->generateSasUri(
            BlobSasBuilder::new()
                ->setPermissions("r")
                ->setExpiresOn((new \DateTime())->modify("+ 1min")),
        );

        $sasBlobClient = new BlobClient($sas);

        $sasBlobClient->downloadStreaming();
    }

    #[Test]
    public function set_tags_works(): void
    {
        $this->blobClient->upload("");
        $this->blobClient->setTags(['foo' => 'bar', 'baz' => 'boo']);

        $tags = $this->blobClient->getTags();

        $this->assertEquals($tags['foo'], 'bar');
        $this->assertEquals($tags['baz'], 'boo');
    }

    #[Test]
    public function set_tags_throws_when_container_doesnt_exist(): void
    {
        $this->expectException(ContainerNotFoundExceptionBlob::class);

        $this->serviceClient->getContainerClient("noop")->getBlobClient("noop")->setTags([]);
    }

    #[Test]
    public function set_tags_throws_if_blob_doesnt_exist(): void
    {
        $this->expectException(BlobNotFoundExceptionBlob::class);

        $this->blobClient->setTags([]);
    }

    #[Test]
    public function set_tags_throws_when_tag_key_is_too_large(): void
    {
        $this->expectException(TagsTooLargeException::class);

        $this->blobClient->setTags([str_pad("", 1000) => 'noop']);
    }

    #[Test]
    public function set_tags_throws_when_tag_value_is_too_large(): void
    {
        $this->expectException(TagsTooLargeException::class);

        $this->blobClient->setTags(["noop" => str_pad("", 1000)]);
    }

    #[Test]
    public function set_tags_throws_when_too_many_tags_are_provided(): void
    {
        $this->expectException(TagsTooLargeException::class);

        $tags = [];

        for($i = 0; $i < 1000; $i++) {
            $tags["tag-$i"] = "noop";
        }

        $this->blobClient->setTags($tags);
    }

    #[Test]
    public function get_tags_works(): void
    {
        $this->blobClient->upload("");
        $this->blobClient->setTags(['foo' => 'bar', 'baz' => 'boo']);

        $tags = $this->blobClient->getTags();

        $this->assertEquals($tags['foo'], 'bar');
        $this->assertEquals($tags['baz'], 'boo');
    }

    #[Test]
    public function get_tags_throws_when_container_doesnt_exist(): void
    {
        $this->expectException(ContainerNotFoundExceptionBlob::class);

        $this->serviceClient->getContainerClient("noop")->getBlobClient("noop")->getTags();
    }

    #[Test]
    public function get_tags_throws_if_blob_doesnt_exist(): void
    {
        $this->expectException(BlobNotFoundExceptionBlob::class);

        $this->blobClient->getTags();
    }

    #[Test]
    public function set_metadata_works(): void
    {
        $this->blobClient->upload("");
        $props = $this->blobClient->getProperties();

        $this->assertEmpty($props->metadata);

        $this->blobClient->setMetadata(['foo' => 'bar', 'baz' => 'qaz']);

        $props = $this->blobClient->getProperties();

        $this->assertEquals('bar', $props->metadata['foo']);
        $this->assertEquals('qaz', $props->metadata['baz']);
    }

    #[Test]
    public function set_metadata_throws_when_container_doesnt_exist(): void
    {
        $this->expectException(ContainerNotFoundException::class);

        $this->serviceClient->getContainerClient("noop")->getBlobClient("noop")->setMetadata(["foo" => "bar"]);
    }

    #[Test]
    public function set_metadata_throws_if_blob_doesnt_exist(): void
    {
        $this->expectException(BlobNotFoundException::class);

        $this->containerClient->getBlobClient("noop")->setMetadata(["foo" => "bar"]);
    }
}
