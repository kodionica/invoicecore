import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router';
import { useApp } from '../context/AppContext';
import { toast } from 'sonner';

type AuthFormValues = {
  login?: string;
  password: string;
  first_name?: string;
  last_name?: string;
  email?: string;
  password_confirmation?: string;
  remember?: boolean;
};

export default function Auth() {
  const [isLogin, setIsLogin] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const { login, register: registerUser } = useApp();
  const navigate = useNavigate();
  const { register, handleSubmit, formState: { errors } } = useForm<AuthFormValues>();

  const onSubmit = async (data: AuthFormValues) => {
    try {
      setIsSubmitting(true);
      if (isLogin) {
        await login({
          login: data.login || '',
          password: data.password,
          remember: data.remember,
        });
        toast.success('Uspešna prijava');
      } else {
        await registerUser({
          first_name: data.first_name || '',
          last_name: data.last_name || '',
          email: data.email || '',
          password: data.password,
          password_confirmation: data.password_confirmation || '',
        });
        toast.success('Nalog kreiran');
      }
      navigate('/dashboard');
    } catch (error: any) {
      const message = error?.response?.data?.message || 'Došlo je do greške. Pokušaj ponovo.';
      toast.error(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="flex justify-center">
          <div className="h-12 w-12 bg-indigo-600 rounded-lg flex items-center justify-center">
            <span className="font-bold text-2xl text-white">L</span>
          </div>
        </div>
        <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
          {isLogin ? 'Prijavi se na nalog' : 'Registruj se besplatno'}
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          Ili{' '}
          <button
            onClick={() => setIsLogin(!isLogin)}
            className="font-medium text-indigo-600 hover:text-indigo-500"
          >
            {isLogin ? 'započni 14 dana besplatno' : 'se prijavi ako već imaš nalog'}
          </button>
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
          <form className="space-y-6" onSubmit={handleSubmit(onSubmit)}>

            {isLogin ? (
              <div>
                <label htmlFor="login" className="block text-sm font-medium text-gray-700">
                  Email ili korisničko ime
                </label>
                <div className="mt-1">
                  <input
                    id="login"
                    type="text"
                    autoComplete="username"
                    {...register("login", { required: true })}
                    className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                  {errors.login && <p className="text-red-500 text-xs mt-1">Obavezno polje</p>}
                </div>
              </div>
            ) : (
              <>
                <div>
                  <label htmlFor="first_name" className="block text-sm font-medium text-gray-700">
                    Ime
                  </label>
                  <div className="mt-1">
                    <input
                      id="first_name"
                      type="text"
                      {...register("first_name", { required: !isLogin })}
                      className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                    {errors.first_name && <p className="text-red-500 text-xs mt-1">Obavezno polje</p>}
                  </div>
                </div>
                <div>
                  <label htmlFor="last_name" className="block text-sm font-medium text-gray-700">
                    Prezime
                  </label>
                  <div className="mt-1">
                    <input
                      id="last_name"
                      type="text"
                      {...register("last_name", { required: !isLogin })}
                      className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                    {errors.last_name && <p className="text-red-500 text-xs mt-1">Obavezno polje</p>}
                  </div>
                </div>
                <div>
                  <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                    Email adresa
                  </label>
                  <div className="mt-1">
                    <input
                      id="email"
                      type="email"
                      autoComplete="email"
                      {...register("email", { required: !isLogin })}
                      className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                    {errors.email && <p className="text-red-500 text-xs mt-1">Obavezno polje</p>}
                  </div>
                </div>
              </>
            )}

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                Lozinka
              </label>
              <div className="mt-1">
                <input
                  id="password"
                  type="password"
                  autoComplete="current-password"
                  {...register("password", { required: true })}
                  className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
                 {errors.password && <p className="text-red-500 text-xs mt-1">Obavezno polje</p>}
              </div>
            </div>

            {!isLogin && (
              <div>
                <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">
                  Potvrdi lozinku
                </label>
                <div className="mt-1">
                  <input
                    id="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    {...register("password_confirmation", { required: !isLogin })}
                    className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                  {errors.password_confirmation && <p className="text-red-500 text-xs mt-1">Obavezno polje</p>}
                </div>
              </div>
            )}

              <div>
                  <label className="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                      <input
                          type="checkbox"
                          {...register('remember')}
                          className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                      />
                      Zapamti me
                  </label>
              </div>

            <div>
              <button
                type="submit"
                disabled={isSubmitting}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                {isSubmitting ? 'Sačekajte...' : isLogin ? 'Prijavi se' : 'Registruj se'}
              </button>
            </div>
          </form>

          <div className="mt-6">
            <div className="relative">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-gray-300" />
              </div>
              <div className="relative flex justify-center text-sm">
                <span className="px-2 bg-white text-gray-500">
                  Nastavi sa
                </span>
              </div>
            </div>

            <div className="mt-6 grid grid-cols-2 gap-3">
              <div>
                <a
                  href="#"
                  className="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                >
                  <span className="sr-only">Google</span>
                  <svg className="h-5 w-5" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24">
                     <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z" />
                  </svg>
                </a>
              </div>

              <div>
                <a
                  href="#"
                  className="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                >
                  <span className="sr-only">GitHub</span>
                  <svg className="h-5 w-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                     <path fillRule="evenodd" d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z" clipRule="evenodd" />
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
