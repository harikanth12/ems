from django.db import models
from django.contrib.auth.models import User
from django.db.models.signals import post_save
from django.dispatch import receiver
# Create your models here.

class Profile(models.Model):
	user = models.OneToOneField(User,on_delete=models.CASCADE)
	designation = models.CharField(null=False,blank=False,max_length=25)
	salary=models.IntegerField(null=True,blank=True)

	class Meta:
		ordering = ('-salary',)

	def __str__(self):
		return f"{self.user.first_name} {self.user.last_name}"

class New_user(models.Model):
	user = models.OneToOneField(User,on_delete=models.CASCADE)
	department = models.CharField(null=False,blank=False,max_length=25)

@receiver(post_save,sender=User)
def create_profile(sender,instance,created,**kwargs):
	# print("created Profile")
	# print(created,"created")
	# print(instance,"instance")
	if created:
		Profile.objects.create(user=instance)


@receiver(post_save,sender=User)
def save_profile(sender,instance,**kwargs):
	# print("Sa veProfile")
	# print(instance,"instance")
	instance.profile.save()

