class RoleMiddleWare:
	def __init__(self,get_response):
		self.get_response = get_response

	def __call__(self,request):
		response = self.get_response(request)

		return response

	def process_view(self,request,view_fun,*view_args,**view_kwargs):

		if request.user.is_authenticated:
			request.role = None
			groups = request.user.groups.all()
			if groups:
				request.role = groups[0].name

		