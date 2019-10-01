from django import forms
from django.contrib.auth.models import User,Group

class UserForm(forms.ModelForm):
	password = forms.CharField(widget=forms.PasswordInput)
	role = forms.ModelChoiceField(queryset=Group.objects.all())
	print(role)

	class Meta:
		model = User
		fields = ['first_name','last_name','email','password','username','role']

		label = {'password':'Password'}

	def __init__(self,*args,**kwargs):
		print(kwargs,"inital")
		if kwargs.get('instance'):
			initial = kwargs.setdefault('initial',{})
			print(kwargs)
			print(kwargs['instance'].groups.all())
			if kwargs['instance'].groups.all():
				initial['role'] = kwargs['instance'].groups.all()[0]
				print(initial['role'])
			else:
				initial['role'] = None

		forms.ModelForm.__init__(self,*args,**kwargs)

	def save(self):
		password = self.cleaned_data.pop('password')
		role = self.cleaned_data.pop('role')
		u = super().save()
		u.groups.set([role])
		u.set_password(password)
		u.save()
		return u
		