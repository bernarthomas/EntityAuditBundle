<?php
/*
 * (c) 2011 SimpleThings GmbH
 *
 * @package SimpleThings\EntityAudit
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 * @link http://www.simplethings.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

namespace SimpleThings\EntityAudit\Tests;

use Doctrine\ORM\Mapping as ORM;

class RelationTest extends BaseTest
{
    protected $schemaEntities = array(
        'SimpleThings\EntityAudit\Tests\OwnerEntity',
        'SimpleThings\EntityAudit\Tests\OwnedEntity1',
        'SimpleThings\EntityAudit\Tests\OwnedEntity2'
    );

    protected $auditedEntities = array(
        'SimpleThings\EntityAudit\Tests\OwnerEntity',
        'SimpleThings\EntityAudit\Tests\OwnedEntity1',
    );

    public function testRelations()
    {
        $auditReader = $this->auditManager->createAuditReader($this->em);

        //create owner
        $owner = new OwnerEntity();
        $owner->setTitle('rev#1');

        $this->em->persist($owner);
        $this->em->flush();

        $this->assertCount(1, $auditReader->findRevisions(get_class($owner), $owner->getId()));

        //create un-managed entity
        $owned21 = new OwnedEntity2();
        $owned21->setTitle('owned21');
        $owned21->setOwner($owner);

        $this->em->persist($owned21);
        $this->em->flush();

        //should not add a revision
        $this->assertCount(1, $auditReader->findRevisions(get_class($owner), $owner->getId()));

        $owner->setTitle('changed#2');

        $this->em->flush();

        //should add a revision
        $this->assertCount(2, $auditReader->findRevisions(get_class($owner), $owner->getId()));

        $owned11 = new OwnedEntity1();
        $owned11->setTitle('created#3');
        $owned11->setOwner($owner);

        $this->em->persist($owned11);

        $this->em->flush();

        //should not add a revision for owner
        $this->assertCount(2, $auditReader->findRevisions(get_class($owner), $owner->getId()));
        //should add a revision for owned
        $this->assertCount(1, $auditReader->findRevisions(get_class($owned11), $owner->getId()));

        //should not mess foreign keys
        $this->assertEquals($owner->getId(), $this->em->getConnection()->fetchAll('SELECT strange_owned_id_name FROM OwnedEntity1')[0]['strange_owned_id_name']);
        $this->em->refresh($owner);
        $this->assertCount(1, $owner->getOwned1());
        $this->assertCount(1, $owner->getOwned2());

        //we have a third revision where Owner with title changed#2 has one owned2 and one owned1 entity with title created#3
        $owned12 = new OwnedEntity1();
        $owned12->setTitle('created#4');
        $owned12->setOwner($owner);

        $this->em->persist($owned12);
        $this->em->flush();

        //we have a forth revision where Owner with title changed#2 has one owned2 and two owned1 entities (created#3, created#4)
        $owner->setTitle('changed#5');

        $this->em->flush();
        //we have a fifth revision where Owner with title changed#5 has one owned2 and two owned1 entities (created#3, created#4)

        $owner->setTitle('changed#6');
        $owned12->setTitle('changed#6');

        $this->em->flush();

        $this->em->remove($owned11);
        $owned12->setTitle('changed#7');
        $owner->setTitle('changed#7');
        $this->em->flush();
        //we have a seventh revision where Owner with title changed#7 has one owned2 and one owned1 entity (changed#7)

        //checking third revision
        $audited = $auditReader->find(get_class($owner), $owner->getId(), 3);
        $this->assertEquals('changed#2', $audited->getTitle());
        $this->assertCount(1, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $this->assertEquals('created#3', $audited->getOwned1()[0]->getTitle());
        $this->assertEquals('owned21', $audited->getOwned2()[0]->getTitle());

        //checking forth revision
        $audited = $auditReader->find(get_class($owner), $owner->getId(), 4);
        $this->assertEquals('changed#2', $audited->getTitle());
        $this->assertCount(2, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $this->assertEquals('created#3', $audited->getOwned1()[0]->getTitle());
        $this->assertEquals('created#4', $audited->getOwned1()[1]->getTitle());
        $this->assertEquals('owned21', $audited->getOwned2()[0]->getTitle());

        //checking fifth revision
        $audited = $auditReader->find(get_class($owner), $owner->getId(), 5);
        $this->assertEquals('changed#5', $audited->getTitle());
        $this->assertCount(2, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $this->assertEquals('created#3', $audited->getOwned1()[0]->getTitle());
        $this->assertEquals('created#4', $audited->getOwned1()[1]->getTitle());
        $this->assertEquals('owned21', $audited->getOwned2()[0]->getTitle());

        //checking sixth revision
        $audited = $auditReader->find(get_class($owner), $owner->getId(), 6);
        $this->assertEquals('changed#6', $audited->getTitle());
        $this->assertCount(2, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $this->assertEquals('created#3', $audited->getOwned1()[0]->getTitle());
        $this->assertEquals('changed#6', $audited->getOwned1()[1]->getTitle());
        $this->assertEquals('owned21', $audited->getOwned2()[0]->getTitle());

        //checking seventh revision
        $audited = $auditReader->find(get_class($owner), $owner->getId(), 7);
        $this->assertEquals('changed#7', $audited->getTitle());
        $this->assertCount(1, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $this->assertEquals('changed#7', $audited->getOwned1()[0]->getTitle());
        $this->assertEquals('owned21', $audited->getOwned2()[0]->getTitle());

        //todo: test detaching of entities
    }

    public function testOneXRelations()
    {
        $auditReader = $this->auditManager->createAuditReader($this->em);

        $owner = new OwnerEntity();
        $owner->setTitle('owner');

        $owned = new OwnedEntity1();
        $owned->setTitle('owned');
        $owned->setOwner($owner);

        $this->em->persist($owner);
        $this->em->persist($owned);

        $this->em->flush();
        //first revision done

        $owner->setTitle('changed#2');
        $owned->setTitle('changed#2');
        $this->em->flush();

        //checking first revision
        $audited = $auditReader->find(get_class($owned), $owner->getId(), 1);
        $this->assertEquals('owned', $audited->getTitle());
        $this->assertEquals('owner', $audited->getOwner()->getTitle());

        //checking second revision
        $audited = $auditReader->find(get_class($owned), $owner->getId(), 2);

        $this->assertEquals('changed#2', $audited->getTitle());
        $this->assertEquals('changed#2', $audited->getOwner()->getTitle());
    }
}

/** @ORM\Entity */
class OwnerEntity
{
    /** @ORM\Id @ORM\Column(type="integer", name="some_strange_key_name") @ORM\GeneratedValue(strategy="AUTO") */
    protected $id;

    /** @ORM\Column(type="string", name="crazy_title_to_mess_up_audit") */
    protected $title;

    /** @ORM\OneToMany(targetEntity="OwnedEntity1", mappedBy="owner") */
    protected $owned1;

    /** @ORM\OneToMany(targetEntity="OwnedEntity2", mappedBy="owner") */
    protected $owned2;

    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getOwned1()
    {
        return $this->owned1;
    }

    public function addOwned1($owned1)
    {
        $this->owned1[] = $owned1;
    }

    public function getOwned2()
    {
        return $this->owned2;
    }

    public function addOwned2($owned2)
    {
        $this->owned2[] = $owned2;
    }
}

/** @ORM\Entity */
class OwnedEntity1
{
    /** @ORM\Id @ORM\Column(type="integer", name="strange_owned_id_name") @ORM\GeneratedValue(strategy="AUTO") */
    protected $id;

    /** @ORM\Column(type="string", name="even_strangier_column_name") */
    protected $title;

    /** @ORM\ManyToOne(targetEntity="OwnerEntity") @ORM\JoinColumn(name="owner_id_goes_here", referencedColumnName="some_strange_key_name") */
    protected $owner;

    /** @ORM\OneToMany(targetEntity="OwnedEntity2", mappedBy="owner1") @ORM\JoinColumn(name="well_just_a_foreign_key", referencedColumnName="strange_owned_id_name") */
    protected $owned2;

    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

    public function getOwned2()
    {
        return $this->owned2;
    }

    public function addOwned2($owned2)
    {
        $this->owned2[] = $owned2;
    }
}

/** @ORM\Entity */
class OwnedEntity2
{
    /** @ORM\Id @ORM\Column(type="integer", name="strange_owned_id_name") @ORM\GeneratedValue(strategy="AUTO") */
    protected $id;

    /** @ORM\Column(type="string", name="even_strangier_column_name") */
    protected $title;

    /** @ORM\ManyToOne(targetEntity="OwnerEntity") @ORM\JoinColumn(name="owner_id_goes_here", referencedColumnName="some_strange_key_name")*/
    protected $owner;

    /** @ORM\ManyToOne(targetEntity="OwnedEntity1") @ORM\JoinColumn(name="another_strange_owned_id_name", referencedColumnName="strange_owned_id_name") */
    protected $owner1;

    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

    public function getOwner1()
    {
        return $this->owner1;
    }

    public function setOwner1($owner1)
    {
        $this->owner1 = $owner1;
    }
}
