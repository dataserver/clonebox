<?php
class File
{
    private $id;
    private $folder_id;
    private $name;
    private $normalized;
    private $extension;
    private $size;
    private $format;
    private $thumbnail;
    private $saved_as;
    private $lastmodified_on;
    private $created_on;


    public function __construct(
        $name,
        $extension,
        $size,
        $format,
        $saved_as,
        $thumbnail = null,
        $created_on,
        $lastmodified_on,
        $folder_id = 1,
        $id = 0
    ) {
        $this->id= $id;
        $this->setFolderId($folder_id);
        $this->setName($name);
        $this->setNormalized($name);
        $this->setExtension($extension);
        $this->setSize($size);
        $this->setFormat($format);
        $this->setSavedAs($saved_as);
        $this->setThumbnail($thumbnail);
        $this->setCreatedOn($created_on);
        $this->setLastmodifiedOn($lastmodified_on);
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFolderId(): ?int
    {
        return $this->folder_id;
    }

    public function setFolderId(int $folder_id): self
    {
        $this->folder_id = $folder_id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        // $this->name = $name;
        $name = $this->_getFileName($this->_sanitizeFilename($name));
        $this->name = $name . '.' . $this->extension;

        return $this;
    }

    public function getNormalized(): ?string
    {
        return $this->normalized;
    }

    public function setNormalized(string $normalized): self
    {
        $this->normalized = strtoupper(Urlify::normalize_name($normalized, false));

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        // $info = pathinfo($extension);
        // $info['extension']
        $this->extension = $extension;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getSavedAs(): ?string
    {
        return $this->saved_as;
    }

    public function setSavedAs(string $saved_as): self
    {
        $this->saved_as = $saved_as;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): self
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function getLastmodifedOn(): ?int
    {
        return $this->lastmodified_on;
    }

    public function setLastmodifiedOn(int $lastmodified_on): self
    {
        $this->lastmodified_on = $lastmodified_on;

        return $this;
    }

    public function getCreatedOn(): ?int
    {
        return $this->created_on;
    }

    public function setCreatedOn(int $created_on): self
    {
        $this->created_on = $created_on;

        return $this;
    }


    /**
     * Sanitize file name, select if allow relative_path
     *
     * @param string  $str
     * @param bool    $relative_path allow or not relative paths in $str
     * @return string filterd string
     */
    private function _sanitizeFilename($str, $relative_path = false)
    {
        $bad = [
            '../',
            '<!--',
            '-->',
            '<',
            '>',
            "'",
            '"',
            '&',
            '$',
            '#',
            '{',
            '}',
            '[',
            ']',
            '=',
            ';',
            '?',
            '%20',
            '%22',
            '%3c', // <
            '%253c', // <
            '%3e', // >
            '%0e', // >
            '%28', // (
            '%29', // )
            '%2528', // (
            '%26', // &
            '%24', // $
            '%3f', // ?
            '%3b', // ;
            '%3d', // =
        ];

        if (! $relative_path) {
            $bad[] = './';
            $bad[] = '/';
        }

        $str = $this->_removeInnvisibleCharacters($str, false);

        do {
            $old = $str;
            $str = str_replace($bad, '', $str);
        } while ($old !== $str);

        return stripslashes($str);
    }

    /**
     * Get file name without extension
     *
     * @example        asdf/fileABC.jpg => fileABC
     * @param string   $file name or path
     * @return string
     */
    private function _getFileName(string $file)
    {
        $info = pathinfo($file);

        return basename($file, "." . $info['extension']);
    }

    /**
     * Get file extension
     *
     * @example        asdf/something.jpg => jpg
     * @param string   $file/$file path
     * @return string  extension
     */
    private function _getFileExtension(string $file)
    {
        $info = pathinfo($file);

        return $info['extension'];
    }

    /**
     * mb version of basename() Returns trailing name component of path
     * @param string   $path
     * @return string
     */
    private function _mbBasename($path)
    {
        if (preg_match('@^.*[\\\\/]([^\\\\/]+)$@s', $path, $matches)) {
            return $matches[1];
        } elseif (preg_match('@^([^\\\\/]+)$@s', $path, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Remove invisible characters from a string, use for file names
     *
     * @param string   $str
     * @return string  filtered string
     */
    private function _removeInnvisibleCharacters($str, $url_encoded = true)
    {
        $non_displayables = [];

        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/';  // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/';   // url encoded 16-31
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';   // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }


}
