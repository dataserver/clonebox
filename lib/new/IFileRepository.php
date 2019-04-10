<?php

interface IFileRepository
{
    public function add(File $file);
    public function findAll(array $params): array;
    public function find(int $id);
    // public function findByName(string $name);
    public function findByIds(array $ids): array;
    public function update(File $file);
    public function remove(File $file);
    public function removeBatch(array $ids);


}
