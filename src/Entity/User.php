<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, PasswordUpgraderInterface
{
    #[Groups(["show_basic_user", "show_full_user"])]
    #[ORM\Id]
    #[ORM\Column(name: "user_id", type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    private ?Uuid $user_id = null;

    #[Groups(["show_basic_user", "show_full_user"])]
    #[ORM\Column(length: 180)]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[Groups(["show_full_user"])]
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Bankroll $bankroll = null;

    /**
     * @var Collection<int, TablePlayers>
     */
    #[ORM\OneToMany(targetEntity: TablePlayers::class, mappedBy: 'user')]
    private Collection $tablePlayers;

    public function __construct()
    {
        $this->tablePlayers = new ArrayCollection();
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getRawUserId(): ?Uuid
    {
        return $this->user_id;
    }

    public function getUserId(): ?string
    {
        return $this->user_id?->toString() ?? null;
    }

    public function setUserId(Uuid $user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
    }

    #[Groups(["show_full_user"])]
    public function getBankroll(): ?Bankroll
    {
        return $this->bankroll;
    }

    public function setBankroll(Bankroll $bankroll): static
    {
        // set the owning side of the relation if necessary
        if ($bankroll->getUser() !== $this) {
            $bankroll->setUser($this);
        }

        $this->bankroll = $bankroll;

        return $this;
    }

    /**
     * @return Collection<int, TablePlayers>
     */
    public function getTablePlayers(): Collection
    {
        return $this->tablePlayers;
    }

    public function addTablePlayer(TablePlayers $tablePlayer): static
    {
        if (!$this->tablePlayers->contains($tablePlayer)) {
            $this->tablePlayers->add($tablePlayer);
            $tablePlayer->setUser($this);
        }

        return $this;
    }

    public function removeTablePlayer(TablePlayers $tablePlayer): static
    {
        if ($this->tablePlayers->removeElement($tablePlayer)) {
            // set the owning side to null (unless already changed)
            if ($tablePlayer->getUser() === $this) {
                $tablePlayer->setUser(null);
            }
        }

        return $this;
    }
}
